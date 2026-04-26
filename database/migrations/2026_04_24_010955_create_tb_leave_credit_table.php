<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tb_leave_credit', function (Blueprint $table) {
            $table->id();

            $table->string('EmployeeNo', 50);

            // Vacation Leave
            $table->decimal('VLCredits', 10, 2)->default(0);
            $table->decimal('VLBalance', 10, 2)->unsigned()->default(0);
            $table->decimal('VLFiled', 10, 2)->default(0);

            // Sick Leave
            $table->decimal('SLCredits', 10, 2)->default(0);
            $table->decimal('SLBalance', 10, 2)->unsigned()->default(0);
            $table->decimal('SLFiled', 10, 2)->default(0);

            // Emergency Leave
            $table->decimal('ELCredits', 10, 2)->default(0);
            $table->decimal('ELBalance', 10, 2)->unsigned()->default(0);
            $table->decimal('ELFiled', 10, 2)->default(0);

            // Maternity Leave
            $table->decimal('MLCredits', 10, 2)->default(0);
            $table->decimal('MLBalance', 10, 2)->unsigned()->default(0);
            $table->decimal('MLFiled', 10, 2)->default(0);

            // Paternity Leave
            $table->decimal('PLCredits', 10, 2)->default(0);
            $table->decimal('PLBalance', 10, 2)->unsigned()->default(0);
            $table->decimal('PLFiled', 10, 2)->default(0);

            // Bereavement Leave
            $table->decimal('BLCredits', 10, 2)->default(0);
            $table->decimal('BLBalance', 10, 2)->unsigned()->default(0);
            $table->decimal('BLFiled', 10, 2)->default(0);

            // Birthday Leave
            $table->decimal('BDLCredits', 10, 2)->default(0);
            $table->decimal('BDLBalance', 10, 2)->unsigned()->default(0);
            $table->decimal('BDLFiled', 10, 2)->default(0);

            // Other Leave
            $table->decimal('OLCredits', 10, 2)->default(0);
            $table->decimal('OLBalance', 10, 2)->default(0);
            $table->decimal('OLFiled', 10, 2)->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tb_leave_credit');
    }
};