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
        Schema::create('tb_leave_record', function (Blueprint $table) {
            $table->id();

            $table->string('RequestId', 50);
            $table->string('EmployeeNo', 50);
            $table->string('EmployeeName', 50);

            $table->date('DateFiled');
            $table->date('DateFrom');
            $table->date('DateTo');

            $table->decimal('TotalDays', 5, 2);

            $table->string('LeaveDuration', 10); // Full Day / Half Day
            $table->string('LeaveType', 50);     // VL, SL, etc

            $table->string('Reason', 255);
            $table->string('Remarks', 255)->nullable();
            
            $table->string('Status', 50)->default('Pending');

            $table->string('ApprovedBy', 50)->nullable();
            $table->date('ApprovedDate')->nullable();

            $table->string('DisapprovalReason', 255)->nullable();
            $table->string('Attachment', 255)->nullable();

            $table->timestamps(); // 🔥 better than none
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tb_leave_record');
    }
};
