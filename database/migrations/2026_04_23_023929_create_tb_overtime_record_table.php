<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('tb_overtime_record', function (Blueprint $table) {
            $table->id();

            $table->string('RequestId', 50);
            $table->date('DateFiled');

            $table->string('EmployeeNo', 50);
            $table->string('EmployeeName', 50);

            $table->date('OvertimeDate');
            $table->time('TimeFrom');
            $table->time('TimeTo');

            $table->string('TotalHours', 10);

            $table->string('OvertimeReason', 500);

            $table->string('Status', 20)->default('Pending');

            $table->string('ApprovedBy', 20)->nullable();
            $table->date('ApprovedDate')->nullable();

            $table->string('Remarks', 500)->nullable();
            $table->string('DisapprovalReason', 500)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tb_overtime_record');
    }
};
