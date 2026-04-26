<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_employee_list', function (Blueprint $table) {

            $table->string('EmployeeNo')->primary();

            $table->string('Status')->default('ACTIVE');

            $table->string('FirstName');
            $table->string('MiddleInitial')->nullable();
            $table->string('LastName');

            $table->text('HomeAddress')->nullable();
            $table->date('Birthday')->nullable();

            $table->string('Gender')->nullable();
            $table->string('CivilStatus')->nullable();

            $table->string('ContactNumber')->nullable();
            $table->string('EmailAddress')->unique();

            $table->date('DateHired')->nullable();

            $table->string('Department')->nullable();
            $table->string('CompanyStatus')->nullable();

            $table->string('Position')->nullable();
            $table->string('JobLevel')->nullable();

            $table->decimal('MonthlySalary', 10, 2)->nullable();

            $table->string('SSSNumber')->nullable();
            $table->string('PhilHealthNumber')->nullable();
            $table->string('PagIbigNumber')->nullable();
            $table->string('TIN')->nullable();

            $table->boolean('IsDeleted')->default(false);

            $table->timestamps(); // 🔥 optional but recommended
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_employee_list');
    }
};