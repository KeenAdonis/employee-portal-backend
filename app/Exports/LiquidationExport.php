<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class LiquidationExport implements
    FromCollection,
    WithHeadings
{
    protected $query;

    public function __construct($query)
    {
        $this->query = $query;
    }

    public function collection()
    {
        return $this->query
            ->with('requisition')
            ->get()
            ->map(function ($item) {

                return [

                    'Reference' =>
                        $item->request_id,

                    'Employee' =>
                        optional(
                            $item->requisition
                        )->EmployeeName,

                    'Department' =>
                        optional(
                            $item->requisition
                        )->Department,

                    'Cash Advance' =>
                        number_format(
                            $item->cash_advance,
                            2
                        ),

                    'Expenses' =>
                        number_format(
                            $item->total_expenses,
                            2
                        ),

                    'Returned' =>
                        number_format(
                            $item->amount_returned,
                            2
                        ),

                    'Reimbursement' =>
                        number_format(
                            $item->amount_reimbursement,
                            2
                        ),

                    'Status' =>
                        $item->status,

                    'Date Filed' =>
                        optional(
                            $item->created_at
                        )->format('F d, Y'),
                ];
            });
    }

    public function headings(): array
    {
        return [

            'Reference',

            'Employee',

            'Department',

            'Cash Advance',

            'Expenses',

            'Returned',

            'Reimbursement',

            'Status',

            'Date Filed',
        ];
    }
}