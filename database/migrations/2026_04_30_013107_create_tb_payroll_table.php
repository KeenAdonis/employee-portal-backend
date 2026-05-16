<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tb_payroll', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('employee_id')->nullable();
            $table->string('EmployeeNo')->nullable();
            $table->string('EmployeeName')->nullable();
            $table->string('Position')->nullable();

            $table->string('CompanyStatus')->nullable();
            $table->string('Type')->nullable();

            $table->date('payroll_period_start')->nullable();
            $table->date('payroll_period_end')->nullable();
            $table->date('PayDate');

            $table->decimal('MonthlySalary', 12, 2)->default(0);
            $table->decimal('BiMonthlySalary', 12, 2)->default(0);

            $table->decimal('Gross', 12, 2)->default(0);
            $table->decimal('NetPay', 12, 2)->default(0);

            $table->decimal('TotalOvertime', 12, 2)->default(0);
            $table->decimal('TotalPerDay', 12, 2)->default(0);
            $table->decimal('TotalDeMinimis', 12, 2)->default(0);
            $table->decimal('TotalOvertimeAndPerDay', 12, 2)->default(0);
            $table->decimal('TotalDeduction', 12, 2)->default(0);

            // OVERTIME
            $table->decimal('OTRegularDay', 12, 2)->default(0);
            $table->decimal('OTRestDay', 12, 2)->default(0);
            $table->decimal('OTSpecialNonWorkingDay', 12, 2)->default(0);
            $table->decimal('OTSpecialNonWorkingAndRestDay', 12, 2)->default(0);
            $table->decimal('OTRegularHoliday', 12, 2)->default(0);
            $table->decimal('OTRegularHolidayAndRestDay', 12, 2)->default(0);

            // PER DAY
            $table->decimal('PDRestDay', 12, 2)->default(0);
            $table->decimal('PDSpecialNonWorkingDay', 12, 2)->default(0);
            $table->decimal('PDSpecialNonWorkingAndRestDay', 12, 2)->default(0);
            $table->decimal('PDRegularHoliday', 12, 2)->default(0);
            $table->decimal('PDRegularHolidayAndRestDay', 12, 2)->default(0);

            // DEDUCTIONS
            $table->decimal('Absences', 12, 2)->default(0);
            $table->decimal('Tardiness', 12, 2)->default(0);
            $table->decimal('Undertime', 12, 2)->default(0);

            // DE MINIMIS
            $table->decimal('RiceSubsidy', 12, 2)->default(0);
            $table->decimal('LoadAllowance', 12, 2)->default(0);
            $table->decimal('MedicalReimbursement', 12, 2)->default(0);
            $table->decimal('TripTicket', 12, 2)->default(0);
            $table->decimal('Additional', 12, 2)->default(0);

            // BENEFITS
            $table->decimal('SSS', 12, 2)->default(0);
            $table->decimal('PhilHealth', 12, 2)->default(0);
            $table->decimal('Pagibig', 12, 2)->default(0);
            $table->decimal('Tax', 12, 2)->default(0);
            $table->decimal('SSSWisp', 12, 2)->default(0);
            $table->decimal('HMO', 12, 2)->default(0);

            // LOANS
            $table->decimal('SalaryLoanPayment', 12, 2)->default(0);
            $table->decimal('LaptopLoanPayment', 12, 2)->default(0);
            $table->decimal('DeductionPayment', 12, 2)->default(0);
            $table->decimal('SSSPersonalLoanPayment', 12, 2)->default(0);
            $table->decimal('SSSCalamityLoanPayment', 12, 2)->default(0);
            $table->decimal('PagIbigPersonalLoanPayment', 12, 2)->default(0);
            $table->decimal('PagIbigCalamityLoanPayment', 12, 2)->default(0);

            $table->enum('status', ['draft', 'approved', 'paid'])->default('draft');

            $table->timestamps();

            // OPTIONAL INDEXES
            $table->index('EmployeeNo');
            $table->index('PayDate');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_payroll');
    }
};