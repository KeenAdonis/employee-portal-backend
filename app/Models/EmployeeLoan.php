<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeLoan extends Model
{
    protected $table = 'tb_employee_loans';

    protected $fillable = [
        'employee_id',
        'reference_no',
        'loan_type',
        'principal_amount',
        'interest_amount',
        'total_amount',
        'terms',
        'monthly_amortization',
        'balance',
        'cutoff_type',
        'start_date',
        'status',
        'remarks',
    ];

    protected $casts = [
        'principal_amount' => 'float',
        'interest_amount' => 'float',
        'total_amount' => 'float',
        'monthly_amortization' => 'float',
        'balance' => 'float',
        'start_date' => 'date',
    ];

    /* =========================
       RELATIONSHIPS
    ========================= */

    public function employee()
    {
        return $this->belongsTo(
            Employee::class,
            'employee_id',
            'employee_id'
        );
    }

    public function payments()
    {
        return $this->hasMany(
            EmployeeLoanPayment::class,
            'loan_id',
            'id'
        );
    }
}