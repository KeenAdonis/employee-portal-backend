<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Employee;
use App\Models\PayrollItem;

class Payroll extends Model
{
    protected $table = 'tb_payroll';

    // 🔐 MAS SAFE kaysa fillable
    protected $guarded = ['id'];

    // 🔥 AUTO CAST (VERY IMPORTANT FOR PAYROLL)
    protected $casts = [
        'MonthlySalary' => 'float',
        'BiMonthlySalary' => 'float',
        'Gross' => 'float',
        'NetPay' => 'float',

        'TotalOvertime' => 'float',
        'TotalPerDay' => 'float',
        'TotalDeMinimis' => 'float',
        'TotalOvertimeAndPerDay' => 'float',
        'TotalDeduction' => 'float',

        // OT
        'OTRegularDay' => 'float',
        'OTRestDay' => 'float',
        'OTSpecialNonWorkingDay' => 'float',
        'OTSpecialNonWorkingAndRestDay' => 'float',
        'OTRegularHoliday' => 'float',
        'OTRegularHolidayAndRestDay' => 'float',

        // PD
        'PDRestDay' => 'float',
        'PDSpecialNonWorkingDay' => 'float',
        'PDSpecialNonWorkingAndRestDay' => 'float',
        'PDRegularHoliday' => 'float',
        'PDRegularHolidayAndRestDay' => 'float',

        // Deductions
        'Absences' => 'float',
        'Tardiness' => 'float',
        'Undertime' => 'float',

        // De Minimis
        'RiceSubsidy' => 'float',
        'LoadAllowance' => 'float',
        'MedicalReimbursement' => 'float',
        'TripTicket' => 'float',
        'Additional' => 'float',

        // Benefits
        'SSS' => 'float',
        'PhilHealth' => 'float',
        'Pagibig' => 'float',
        'Tax' => 'float',
        'SSSWisp' => 'float',
        'HMO' => 'float',

        // Loans
        'SalaryLoanPayment' => 'float',
        'LaptopLoanPayment' => 'float',
        'DeductionPayment' => 'float',
        'SSSPersonalLoanPayment' => 'float',
        'SSSCalamityLoanPayment' => 'float',
        'PagIbigPersonalLoanPayment' => 'float',
        'PagIbigCalamityLoanPayment' => 'float',
    ];

    public function items()
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function employee()
    {
        return $this->belongsTo(
            Employee::class,
            'EmployeeNo',
            'EmployeeNo'
        );
    }
}