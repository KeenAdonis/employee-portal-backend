<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;

class PayrollExport implements FromArray, WithHeadings, WithStyles, WithEvents
{
    protected $from;
    protected $to;

    public function __construct($from, $to)
    {
        $this->from = $from;
        $this->to = $to;
    }

    public function headings(): array
    {
        return [
            'Name',
            'Position',
            'Payroll Period',
            'Bi-Monthly Salary',

            'Total Overtime',
            'De Minimis',
            'Absences',
            'Tardiness',
            'Undertime',
            'HMO',

            'Salary Loan',
            'Laptop Loan',
            'SSS Personal Loan',
            'SSS Calamity Loan',
            'Pagibig Personal Loan',
            'Pagibig Calamity Loan',

            'SSS',
            'PhilHealth',
            'Pagibig',
            'SSS Wisp',
            'Tax',

            'Gross Pay',
            'Total Deduction',
            'Net Pay',
        ];
    }

    public function array(): array
    {
        $rows = DB::table('tb_payroll')
            ->whereDate('PayDate', '>=', $this->from)
            ->whereDate('PayDate', '<=', $this->to)
            ->get();

        $data = [];

        $totals = [
            'sss' => 0,
            'philhealth' => 0,
            'pagibig' => 0,
            'wisp' => 0,
            'tax' => 0,

            'gross' => 0,
            'deduction' => 0,
            'net' => 0,
        ];

        foreach ($rows as $row) {

            // 🔥 LOAN PAYMENTS
            $loanPayments = DB::table('tb_employee_loans_payments as p')
                ->join('tb_employee_loans as l', 'l.id', '=', 'p.loan_id')
                ->whereDate('p.deduction_date', $row->PayDate)
                ->where('l.employee_id', $row->employee_id)
                ->select('l.loan_type', DB::raw('SUM(p.amount) as total'))
                ->groupBy('l.loan_type')
                ->pluck('total', 'loan_type');

            $salaryLoan = $loanPayments['Salary Loan'] ?? 0;
            $laptopLoan = $loanPayments['Laptop Loan'] ?? 0;
            $sssPersonal = $loanPayments['SSS Personal Loan'] ?? 0;
            $sssCalamity = $loanPayments['SSS Calamity Loan'] ?? 0;
            $pagibigPersonal = $loanPayments['Pagibig Personal Loan'] ?? 0;
            $pagibigCalamity = $loanPayments['Pagibig Calamity Loan'] ?? 0;

            $data[] = [
                $row->EmployeeName ?? '',
                $row->Position ?? '',
                $row->PayDate ?? '',
                number_format((float) ($row->BiMonthlySalary ?? 0), 2, '.', ''),

                number_format((float) ($row->TotalOvertime ?? 0), 2, '.', ''),
                number_format((float) ($row->TotalDeMinimis ?? 0), 2, '.', ''),
                number_format((float) ($row->Absences ?? 0), 2, '.', ''),
                number_format((float) ($row->Tardiness ?? 0), 2, '.', ''),
                number_format((float) ($row->Undertime ?? 0), 2, '.', ''),
                number_format((float) ($row->HMO ?? 0), 2, '.', ''),

                number_format((float) $salaryLoan, 2, '.', ''),
                number_format((float) $laptopLoan, 2, '.', ''),
                number_format((float) $sssPersonal, 2, '.', ''),
                number_format((float) $sssCalamity, 2, '.', ''),
                number_format((float) $pagibigPersonal, 2, '.', ''),
                number_format((float) $pagibigCalamity, 2, '.', ''),

                number_format((float) ($row->SSS ?? 0), 2, '.', ''),
                number_format((float) ($row->PhilHealth ?? 0), 2, '.', ''),
                number_format((float) ($row->Pagibig ?? 0), 2, '.', ''),
                number_format((float) ($row->SSSWisp ?? 0), 2, '.', ''),
                number_format((float) ($row->Tax ?? 0), 2, '.', ''),

                number_format((float) ($row->Gross ?? 0), 2, '.', ''),
                number_format((float) ($row->TotalDeduction ?? 0), 2, '.', ''),
                number_format((float) ($row->NetPay ?? 0), 2, '.', ''),
            ];

            // 🔥 TOTALS
            $totals['sss'] += (float) ($row->SSS ?? 0);
            $totals['philhealth'] += (float) ($row->PhilHealth ?? 0);
            $totals['pagibig'] += (float) ($row->Pagibig ?? 0);
            $totals['wisp'] += (float) ($row->SSSWisp ?? 0);
            $totals['tax'] += (float) ($row->Tax ?? 0);

            $totals['gross'] += (float) ($row->Gross ?? 0);
            $totals['deduction'] += (float) ($row->TotalDeduction ?? 0);
            $totals['net'] += (float) ($row->NetPay ?? 0);
        }

        // 🔥 COMPUTE GMB
        $totals['gmb'] =
            $totals['sss'] +
            $totals['philhealth'] +
            $totals['pagibig'] +
            $totals['wisp'] +
            $totals['tax'];

        // 🔥 TOTAL ROW
        $data[] = [
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            'TOTAL:',

            number_format($totals['sss'], 2, '.', ''),
            number_format($totals['philhealth'], 2, '.', ''),
            number_format($totals['pagibig'], 2, '.', ''),
            number_format($totals['wisp'], 2, '.', ''),
            number_format($totals['tax'], 2, '.', ''),

            number_format($totals['gross'], 2, '.', ''),
            number_format($totals['deduction'], 2, '.', ''),
            number_format($totals['net'], 2, '.', ''),
        ];

        // 🔥 TOTAL GMB ROW
        $data[] = [
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',


            '', // SSS
            '', // PhilHealth
            '', // Pagibig
            'TOTAL GMB:', // Wisp
            number_format($totals['gmb'], 2, '.', ''),

            '', // Gross
            '', // Deduction
            '', // Net
        ];

        return $data;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {

                $sheet = $event->sheet->getDelegate();

                $lastRow = $sheet->getHighestRow();
                $lastColumn = $sheet->getHighestColumn();

                // ROTATE HEADER
                $sheet->getStyle("A1:{$lastColumn}1")
                    ->getAlignment()
                    ->setTextRotation(90)
                    ->setHorizontal('center')
                    ->setVertical('center');

                $sheet->getRowDimension(1)->setRowHeight(80);

                foreach (range('A', $lastColumn) as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

                $sheet->getStyle("A1:{$lastColumn}{$lastRow}")
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);

                $sheet->getStyle("A1:{$lastColumn}{$lastRow}")
                    ->getAlignment()
                    ->setHorizontal('center');

                // BOLD LAST 2 ROWS (TOTAL + GMB)
                $sheet->getStyle("A" . ($lastRow - 1) . ":{$lastColumn}{$lastRow}")
                    ->getFont()
                    ->setBold(true);

                // FORMAT NUMBERS
                $sheet->getStyle("A2:X{$lastRow}")
                    ->getNumberFormat()
                    ->setFormatCode('#,##0.00;[Red]-#,##0.00;0.00');
            },
        ];
    }
}