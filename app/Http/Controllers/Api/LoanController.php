<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\EmployeeLoan;
use App\Models\Notification;
use App\Events\NotificationCreated;
use App\Models\User;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class LoanController extends Controller
{
    /* =========================
       LIST LOANS
    ========================= */
    public function index(Request $request)
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        $query = EmployeeLoan::with('employee');

        /* =========================
           🔐 ROLE-BASED FILTER
        ========================= */
        if ($user->role === 'employee') {

            $query->whereHas('employee', function ($q) use ($user) {

                $q->where('EmployeeNo', $user->employee_no);
            });
        }

        // adminhr → full access

        /* =========================
           🔍 SEARCH
        ========================= */
        if ($request->filled('search')) {

            $search = $request->search;

            $query->where(function ($q) use ($search) {

                $q->where('reference_no', 'like', "%{$search}%")
                    ->orWhere('loan_type', 'like', "%{$search}%")
                    ->orWhereHas('employee', function ($emp) use ($search) {

                        $emp->where('FirstName', 'like', "%{$search}%")
                            ->orWhere('LastName', 'like', "%{$search}%")
                            ->orWhere('EmployeeNo', 'like', "%{$search}%");
                    });
            });
        }

        /* =========================
           📊 STATUS FILTER
        ========================= */
        if ($request->filled('status')) {

            $query->where('status', $request->status);
        }

        /* =========================
           📄 PAGINATION
        ========================= */
        $loans = $query
            ->latest()
            ->paginate($request->per_page ?? 10);

        return response()->json([
            'success' => true,
            'data' => $loans
        ]);
    }

    /* =========================
       CREATE LOAN
    ========================= */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:tb_employee_list,employee_id'],
            'loan_type' => ['required', 'string', 'max:255'],
            'principal_amount' => ['required', 'numeric', 'min:1'],
            'interest_amount' => ['nullable', 'numeric', 'min:0'],
            'terms' => ['required', 'integer', 'min:1'],
            'cutoff_type' => ['required', Rule::in(['15', '30', 'both'])],
            'start_date' => ['required', 'date'],
            'remarks' => ['nullable', 'string']
        ]);

        return DB::transaction(function () use ($validated) {

            /* =========================
               COMPUTATION (BACKEND ONLY)
            ========================= */
            $principal = $validated['principal_amount'];
            $interest = $validated['interest_amount'] ?? 0;
            $terms = $validated['terms'];
            $cutoff = $validated['cutoff_type'];

            $total = $principal + $interest;
            $monthly = $total / $terms;

            $perDeduction = $cutoff === 'both'
                ? $monthly / 2
                : $monthly;

            /* =========================
               CREATE LOAN
            ========================= */
            $loan = EmployeeLoan::create([
                'employee_id' => $validated['employee_id'],
                'reference_no' => $this->generateReference(),
                'loan_type' => $validated['loan_type'],
                'principal_amount' => $principal,
                'interest_amount' => $interest,
                'total_amount' => $total,
                'terms' => $terms,
                'monthly_amortization' => $monthly,
                'balance' => $total,
                'cutoff_type' => $cutoff,
                'start_date' => $validated['start_date'],
                'remarks' => $validated['remarks'] ?? null,
                'status' => 'Active',
            ]);

            /* =========================
               NOTIFY EMPLOYEE
            ========================= */
            $employee = Employee::find($validated['employee_id']);

            if ($employee) {

                $employeeUser = User::query()
                    ->where('employee_no', $employee->EmployeeNo)
                    ->first();

                if ($employeeUser) {

                    $notification = Notification::create([
                        'user_id' => $employeeUser->id,

                        'type' => 'loan',

                        'title' => 'New Active Loan',

                        'message' =>
                            'You have a new active ' .
                            $loan->loan_type .
                            ' amounting to ₱' .
                            number_format($loan->total_amount, 2) . '.',

                        'related_type' => 'loan',

                        'related_id' => $loan->id,

                        'action_url' => '/dashboard/employee/loan',
                    ]);

                    event(
                        new NotificationCreated(
                            $notification
                        )
                    );
                }
            }

            /* =========================
               RESPONSE
            ========================= */
            return response()->json([
                'message' => 'Loan created successfully.',
                'data' => $loan,
                'computed' => [
                    'monthly_amortization' => round($monthly, 2),
                    'per_deduction' => round($perDeduction, 2),
                    'total_amount' => round($total, 2),
                ]
            ], 201);
        });
    }

    /* =========================
       SHOW LOAN (DETAILS)
    ========================= */
    public function show($id)
    {
        $loan = EmployeeLoan::with('employee', 'payments')
            ->findOrFail($id);

        return response()->json([
            'data' => $loan
        ]);
    }

    /* =========================
       GENERATE SCHEDULE
    ========================= */
    public function schedule($id)
    {
        $loan = EmployeeLoan::findOrFail($id);

        $schedule = $this->generateSchedule(
            $loan->start_date,
            $loan->terms,
            $loan->cutoff_type,
            $loan->monthly_amortization
        );

        return response()->json([
            'data' => $schedule
        ]);
    }



    public function download($id)
    {
        $loan = EmployeeLoan::with('employee')->findOrFail($id);

        $schedule = [];

        $balance = (float) $loan->total_amount;
        $monthly = (float) $loan->monthly_amortization;

        $perCutoff = $loan->cutoff_type === 'both'
            ? $monthly / 2
            : $monthly;

        $date = Carbon::parse($loan->start_date);

        // 🔒 SAFETY LIMIT (prevents infinite loop)
        $maxIterations = 1000;
        $counter = 0;

        while ($balance > 0 && $counter < $maxIterations) {

            // 15th cutoff
            if ($loan->cutoff_type === '15' || $loan->cutoff_type === 'both') {

                $amount = min($perCutoff, $balance);

                $schedule[] = [
                    'date' => $date->copy()->day(15)->format('M d, Y'),
                    'amount' => round($amount, 2)
                ];

                $balance -= $amount;

                if ($balance < 0.01) {
                    $balance = 0;
                }
            }

            if ($balance <= 0)
                break;

            // 30th cutoff
            if ($loan->cutoff_type === '30' || $loan->cutoff_type === 'both') {

                $amount = min($perCutoff, $balance);

                $schedule[] = [
                    'date' => $date->copy()->endOfMonth()->format('M d, Y'),
                    'amount' => round($amount, 2)
                ];

                $balance -= $amount;

                if ($balance < 0.01) {
                    $balance = 0;
                }
            }

            $date->addMonth();
            $counter++;
        }

        $pdf = Pdf::loadView('pdf.loan_schedule', [
            'loan' => $loan,
            'schedule' => $schedule
        ]);

        return $pdf->download('loan-' . $loan->reference_no . '.pdf');
    }

    /* =========================
       HELPER: SCHEDULE
    ========================= */
    private function generateSchedule($startDate, $terms, $cutoff, $monthly)
    {
        $dates = [];
        $current = Carbon::parse($startDate);

        $perDeduction = $cutoff === 'both'
            ? $monthly / 2
            : $monthly;

        for ($i = 0; $i < $terms; $i++) {

            if ($cutoff === 'both') {
                $dates[] = [
                    'date' => $current->copy()->day(15)->toDateString(),
                    'amount' => round($perDeduction, 2)
                ];
                $dates[] = [
                    'date' => $current->copy()->endOfMonth()->toDateString(),
                    'amount' => round($perDeduction, 2)
                ];
            }

            if ($cutoff === '15') {
                $dates[] = [
                    'date' => $current->copy()->day(15)->toDateString(),
                    'amount' => round($perDeduction, 2)
                ];
            }

            if ($cutoff === '30') {
                $dates[] = [
                    'date' => $current->copy()->endOfMonth()->toDateString(),
                    'amount' => round($perDeduction, 2)
                ];
            }

            $current->addMonth();
        }

        return $dates;
    }

    public function employeeLoans($employeeId)
    {
        $loans = EmployeeLoan::where('employee_id', $employeeId)
            ->where('status', 'Active')
            ->get()
            ->map(function ($loan) {

                $paid = $loan->total_amount - $loan->balance;

                return [
                    'id' => $loan->id,
                    'loan_type' => $loan->loan_type,
                    'balance' => (float) $loan->balance,
                    'monthly_amortization' => (float) $loan->monthly_amortization,
                    'total_amount' => (float) $loan->total_amount,
                    'paid_amount' => (float) $paid,
                    'cutoff_type' => $loan->cutoff_type,
                    'start_date' => $loan->start_date,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $loans
        ]);
    }

    /* =========================
       HELPER: REFERENCE NO
    ========================= */
    private function generateReference()
    {
        return 'LN-' . now()->format('Ymd') . '-' . strtoupper(uniqid());
    }
}