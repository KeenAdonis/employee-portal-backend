<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Services\PayrollService;
use App\Http\Requests\StorePayrollRequest;
use Illuminate\Http\Request;
use App\Models\EmployeeLoan;
use App\Models\Notification;
use App\Events\NotificationCreated;
use App\Models\User;
use App\Services\LoanDeductionService;

use PhpOffice\PhpWord\TemplateProcessor;
use Illuminate\Support\Facades\Storage;

use App\Exports\PayrollExport;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;




class PayrollController extends Controller
{

    protected $payrollService;
    protected $loanService;

    public function __construct(
        PayrollService $payrollService,
        LoanDeductionService $loanService
    ) {
        $this->payrollService = $payrollService;
        $this->loanService = $loanService;
    }

    public function store(Request $request)
    {
        try {

            DB::beginTransaction();

            // جلوگیری duplicate payroll
            $exists = Payroll::where('employee_id', $request->employee_id)
                ->where('PayDate', $request->PayDate)
                ->exists();

            $employeeId = $request->employee_id ?? $request->employeeId ?? null;

            if (!$employeeId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee ID is missing'
                ], 422);
            }

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payroll already exists for this cutoff'
                ], 422);
            }



            // ✅ COMPUTE (NO SIDE EFFECTS)
            $computed = $this->payrollService->compute($request->all());

            // ✅ SAVE MAIN PAYROLL
            $payroll = Payroll::create([
                'status' => 'Completed',

                'is_locked' => 1,
                'employee_id' => $request->employee_id,
                'EmployeeNo' => $request->EmployeeNo,
                'EmployeeName' => $request->EmployeeName,
                'Position' => $request->Position,
                'CompanyStatus' => $request->CompanyStatus,
                'PayDate' => $request->PayDate,

                'MonthlySalary' => $request->MonthlySalary,
                'BiMonthlySalary' => $request->MonthlySalary / 2,

                'Gross' => $computed['Gross'],
                'NetPay' => $computed['NetPay'],
                'TotalOvertime' => $computed['TotalOvertime'],
                'TotalPerDay' => $computed['TotalHolidayPay'],
                'TotalDeMinimis' => $computed['TotalDeMinimis'],
                'TotalDeduction' => $computed['TotalDeductions'],

                // Government
                'SSS' => $computed['sssAmount'],
                'SSSWisp' => $computed['sssWispAmount'],
                'PhilHealth' => $computed['philHealthAmount'],
                'Pagibig' => $computed['pagIbigAmount'],
                'HMO' => $computed['hmoAmount'],
                'Tax' => $computed['taxAmount'],
            ]);

            // 🔥 PROCESS LOANS (FIXED VERSION)
            $loans = EmployeeLoan::where('employee_id', $request->employee_id)
                ->where('status', 'Active') // EXACT MATCH
                ->get();

            foreach ($loans as $loan) {
                $this->loanService->processLoan(
                    $loan,
                    $request->PayDate,
                    $payroll->id // 🔥 REQUIRED
                );
            }

            // ✅ SAVE ITEMS
            $this->storeItems($payroll, $computed);

            /* =========================
               NOTIFY EMPLOYEE
            ========================= */
            $employeeUser = User::query()
                ->where('employee_no', $payroll->EmployeeNo)
                ->first();

            if ($employeeUser) {

                $notification = Notification::create([
                    'user_id' => $employeeUser->id,

                    'type' => 'payroll',

                    'title' => 'Payroll Available',

                    'message' =>
                        'Your payroll for cutoff ' .
                        Carbon::parse($payroll->PayDate)
                            ->format('F d, Y') .
                        ' is now uploaded.',

                    'related_type' => 'payroll',

                    'related_id' => $payroll->id,

                    'action_url' => '/dashboard/employee/payroll',
                ]);

                event(
                    new NotificationCreated(
                        $notification
                    )
                );
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payroll saved successfully',
            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }


    public function index(Request $request)
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        $query = Payroll::query();

        /* =========================
           🔐 ROLE-BASED FILTER
        ========================= */
        if ($user->role === 'employee') {

            $query->where('EmployeeNo', $user->employee_no);
        }

        // adminhr / adminaccounting → full access

        /* =========================
           🔍 SEARCH
        ========================= */
        if ($request->filled('search')) {

            $search = $request->search;

            $query->where(function ($q) use ($search) {

                $q->where('EmployeeName', 'like', "%{$search}%")
                    ->orWhere('EmployeeNo', 'like', "%{$search}%");
            });
        }

        /* =========================
           📅 PAY DATE FILTER
        ========================= */
        if ($request->filled('date_from')) {

            $query->whereDate(
                'PayDate',
                $request->date_from
            );
        }

        /* =========================
           📄 PAGINATION
        ========================= */
        $payrolls = $query
            ->with([
                'items',
                'employee',
            ])
            ->orderByDesc('PayDate')
            ->paginate($request->per_page ?? 10);

        return response()->json([
            'success' => true,
            'data' => $payrolls
        ]);
    }

    /* =========================
       STORE ITEMS (EXPANDED)
    ========================= */
    private function storeItems(Payroll $payroll, array $computed)
    {
        $map = [

            // OVERTIME
            ['earning', 'overtime', 'OT Regular Day', 'OTRegularAmount'],
            ['earning', 'overtime', 'OT Rest Day', 'OTRestDayAmount'],
            ['earning', 'overtime', 'OT Special NWD', 'OTSpecialAmount'],
            ['earning', 'overtime', 'OT Special NWD Rest', 'OTSpecialRestAmount'],
            ['earning', 'overtime', 'OT Regular Holiday', 'OTHolidayAmount'],
            ['earning', 'overtime', 'OT Holiday Rest', 'OTHolidayRestAmount'],

            // PER DAY
            ['earning', 'holiday', 'PD Rest Day', 'PDRestDayAmount'],
            ['earning', 'holiday', 'PD Special NWD', 'PDSpecialAmount'],
            ['earning', 'holiday', 'PD Special NWD Rest', 'PDSpecialRestAmount'],
            ['earning', 'holiday', 'PD Regular Holiday', 'PDHolidayAmount'],
            ['earning', 'holiday', 'PD Holiday Rest', 'PDHolidayRestAmount'],

            // ALLOWANCES
            ['earning', 'allowance', 'Rice Subsidy', 'RiceSubsidyAmount'],
            ['earning', 'allowance', 'Load Allowance', 'LoadAllowanceAmount'],
            ['earning', 'allowance', 'Medical', 'MedicalReimbursementAmount'],
            ['earning', 'allowance', 'Trip Ticket', 'TripTicketAmount'],
            ['earning', 'allowance', 'Additional', 'AdditionalAmount'],

            // GOVERNMENT
            ['deduction', 'government', 'SSS', 'sssAmount'],
            ['deduction', 'government', 'PhilHealth', 'philHealthAmount'],
            ['deduction', 'government', 'Pagibig', 'pagIbigAmount'],
            ['deduction', 'government', 'SSS Wisp', 'sssWispAmount'],
            ['deduction', 'government', 'HMO', 'hmoAmount'],

            ['deduction', 'tax', 'Tax', 'taxAmount'],

            // PENALTIES
            ['deduction', 'penalty', 'Absences', 'AbsencesAmount'],
            ['deduction', 'penalty', 'Tardiness', 'TardinessAmount'],
            ['deduction', 'penalty', 'Undertime', 'UndertimeAmount'],

            // LOANS (🔥 FIXED)
            ['deduction', 'loan', 'Salary Loan', 'SalaryLoanAmount'],
            ['deduction', 'loan', 'Laptop Loan', 'LaptopLoanAmount'],
            ['deduction', 'loan', 'Deduction Loan', 'DeductionAmount'],
            ['deduction', 'loan', 'SSS Personal Loan', 'SSSPerLoanAmount'],
            ['deduction', 'loan', 'SSS Calamity Loan', 'SSSCalLoanAmount'],
            ['deduction', 'loan', 'Pagibig Personal Loan', 'PagIbigPerLoanAmount'],
            ['deduction', 'loan', 'Pagibig Calamity Loan', 'PagIbigCalLoanAmount'],
        ];

        $items = [];

        foreach ($map as [$type, $category, $label, $field]) {

            $amount = $computed[$field] ?? 0;

            if ($amount > 0) {
                $items[] = [
                    'payroll_id' => $payroll->id,
                    'type' => $type,
                    'category' => $category,
                    'label' => $label,
                    'amount' => $amount,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }
        }

        PayrollItem::insert($items);
    }

    public function downloadPayslip($id)
    {
        $payroll = Payroll::with('items')
            ->findOrFail($id);

        $templatePath = storage_path(
            'app/templates/payslip-template.docx'
        );

        $template = new TemplateProcessor($templatePath);

        /* =========================
           BASIC INFO
        ========================= */

        $template->setValue(
            'employee_name',
            $payroll->EmployeeName
        );

        $template->setValue(
            'employee_no',
            $payroll->EmployeeNo
        );

        $template->setValue(
            'pay_date',
            Carbon::parse($payroll->PayDate)
                ->format('F d, Y')
        );

        $template->setValue(
            'position',
            $payroll->Position
        );

        /* =========================
           EARNINGS
        ========================= */

        $template->setValue(
            'basic_salary',
            number_format(
                $payroll->BiMonthlySalary,
                2
            )
        );

        $template->setValue(
            'ot_pd',
            number_format(
                $payroll->TotalOvertime +
                $payroll->TotalPerDay,
                2
            )
        );

        $template->setValue(
            'deminimis',
            number_format(
                $payroll->TotalDeMinimis,
                2
            )
        );

        /* =========================
           GOVERNMENT
        ========================= */

        $template->setValue(
            'sss',
            number_format($payroll->SSS, 2)
        );

        $template->setValue(
            'philhealth',
            number_format($payroll->PhilHealth, 2)
        );

        $template->setValue(
            'pagibig',
            number_format($payroll->Pagibig, 2)
        );

        $template->setValue(
            'sss_wisp',
            number_format($payroll->SSSWisp, 2)
        );

        $template->setValue(
            'hmo',
            number_format($payroll->HMO, 2)
        );

        /* =========================
           DEFAULT VALUES
        ========================= */

        $fields = [
            'absences',
            'tardiness',
            'undertime',
            'salary_loan',
            'laptop_loan',
            'deduction',
            'sss_pl',
            'sss_cl',
            'pagibig_pl',
            'pagibig_cl',
        ];

        foreach ($fields as $field) {
            $template->setValue($field, '0.00');
        }

        /* =========================
           PAYROLL ITEMS
        ========================= */

        foreach ($payroll->items as $item) {

            switch ($item->label) {

                case 'Absences':
                    $template->setValue(
                        'absences',
                        number_format($item->amount, 2)
                    );
                    break;

                case 'Tardiness':
                    $template->setValue(
                        'tardiness',
                        number_format($item->amount, 2)
                    );
                    break;

                case 'Undertime':
                    $template->setValue(
                        'undertime',
                        number_format($item->amount, 2)
                    );
                    break;

                case 'Salary Loan':
                    $template->setValue(
                        'salary_loan',
                        number_format($item->amount, 2)
                    );
                    break;

                case 'Laptop Loan':
                    $template->setValue(
                        'laptop_loan',
                        number_format($item->amount, 2)
                    );
                    break;
            }
        }

        /* =========================
           TOTALS
        ========================= */

        $template->setValue(
            'gross_pay',
            number_format($payroll->Gross, 2)
        );

        $template->setValue(
            'total_deductions',
            number_format(
                $payroll->TotalDeduction,
                2
            )
        );

        $template->setValue(
            'net_pay',
            number_format($payroll->NetPay, 2)
        );

        $template->setValue(
            'date_generated',
            now()->format('F d, Y h:i A')
        );

        /* =========================
           SAVE DOCX
        ========================= */

        $filename =
            'Payslip_' .
            $payroll->EmployeeNo;

        $tempDocxPath = storage_path(
            'app/temp/' . $filename . '.docx'
        );

        $template->saveAs($tempDocxPath);

        /* =========================
           CONVERT TO PDF
        ========================= */

        $libreOffice = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN'
            ? '"C:\\Program Files\\LibreOffice\\program\\soffice.exe"'
            : 'libreoffice';

        $command =
            $libreOffice .
            ' --headless --convert-to pdf ' .
            '--outdir ' .
            escapeshellarg(storage_path('app/temp')) .
            ' ' .
            escapeshellarg($tempDocxPath);

        $output = [];

        $returnCode = 0;

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {

            return response()->json([
                'success' => false,
                'message' => 'PDF conversion failed',
                'command' => $command,
                'output' => $output,
                'return_code' => $returnCode,
            ], 500);
        }

        /* =========================
           PDF PATH
        ========================= */

        $pdfPath = storage_path(
            'app/temp/' . $filename . '.pdf'
        );

        /* =========================
           DELETE DOCX TEMP
        ========================= */

        if (file_exists($tempDocxPath)) {
            unlink($tempDocxPath);
        }

        /* =========================
           DOWNLOAD PDF
        ========================= */

        return response()->download(
            $pdfPath
        )->deleteFileAfterSend(true);
    }



    public function export(Request $request)
    {
        if (!$request->from) {
            return response()->json([
                'success' => false,
                'message' => 'Cutoff is required'
            ], 422);
        }

        $cutoff = Carbon::parse($request->from);

        // 🔥 DETERMINE RANGE BASED SA CUTOFF
        if ($cutoff->day == 15) {
            // 1st cutoff: 1–15
            $from = $cutoff->copy()->startOfMonth();   // 1
            $to = $cutoff->copy();                   // 15
        } else {
            // 2nd cutoff: 16–end
            $from = $cutoff->copy()->startOfMonth()->addDays(15); // 16
            $to = $cutoff->copy()->endOfMonth();                // 28–31
        }

        return Excel::download(
            new PayrollExport($from->format('Y-m-d'), $to->format('Y-m-d')),
            'Payroll_Report.xlsx'
        );
    }

    public function preview(Request $request)
    {
        $data = $request->all();

        $result = $this->payrollService->compute($data);

        return response()->json([
            'success' => true,
            'data' => $result
        ]);
    }
}