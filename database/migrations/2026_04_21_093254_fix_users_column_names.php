<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {

            // rename EmployeeNo → employee_no
            if (Schema::hasColumn('users', 'EmployeeNo')) {
                $table->renameColumn('EmployeeNo', 'employee_no');
            }

            // add status if not exists
            if (!Schema::hasColumn('users', 'status')) {
                $table->string('status')->default('ACTIVE');
            }

        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {

            if (Schema::hasColumn('users', 'employee_no')) {
                $table->renameColumn('employee_no', 'EmployeeNo');
            }

            if (Schema::hasColumn('users', 'status')) {
                $table->dropColumn('status');
            }

        });
    }
};
