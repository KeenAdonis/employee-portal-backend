<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeLoanPayment extends Model
{
    protected $table = 'tb_employee_loans_payments';

    protected $fillable = [
        'loan_id',
        'amount',
        'deduction_date',
        'payroll_reference',
    ];

    protected $casts = [
        'amount' => 'float',
        'deduction_date' => 'date',
    ];

    public function loan()
    {
        return $this->belongsTo(
            EmployeeLoan::class,
            'loan_id',
            'id'
        );
    }
}