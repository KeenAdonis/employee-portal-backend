<?php

namespace App\Exports;

use App\Models\Requisition;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class RequisitionExport implements
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
            ->select([
                'RequestId',
                'EmployeeName',
                'Department',
                'Type',
                'TotalAmount',
                'Status',
                'DateFiled',
            ])
            ->get()
            ->map(function ($item) {

                return [
                    'RequestId' => $item->RequestId,
                    'EmployeeName' => $item->EmployeeName,
                    'Department' => $item->Department,
                    'Type' => $item->Type,
                    'TotalAmount' => $item->TotalAmount,
                    'Status' => $item->Status,
                    'DateFiled' => optional(
                        $item->DateFiled
                    )->format('F d, Y'),
                ];
            });
    }

    public function headings(): array
    {
        return [
            'Request ID',
            'Employee',
            'Department',
            'Type',
            'Total Amount',
            'Status',
            'Date Filed',
        ];
    }
}