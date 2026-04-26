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
        Schema::create('tb_requisition', function (Blueprint $table) {
            $table->id();

            // Core
            $table->string('RequestId', 50)->unique();
            $table->enum('Type', [
                'Request for Payment',
                'Cash Advance',
                'Petty Cash',
                'Reimbursement'
            ]);

            $table->string('ControlNo', 50)->nullable();

            $table->date('DateFiled');

            // Employee Info
            $table->string('EmployeeNo', 50)->index();
            $table->string('EmployeeName', 100);
            $table->string('Department', 100);

            // Date Needed
            $table->date('StartDateNeeded');
            $table->date('EndDateNeeded');

            // Amount
            $table->decimal('TotalAmount', 12, 2)->default(0);

            // Details
            $table->string('Remarks', 255)->nullable();

            // Status Flow
            $table->string('Status', 50)->default('Pending')->index();

            $table->date('ReceivedDate')->nullable();

            $table->string('CheckedBy', 100)->nullable();
            $table->date('CheckedDate')->nullable();

            $table->string('ApprovedBy', 100)->nullable();
            $table->date('ApprovedDate')->nullable();

            $table->string('DisapprovalReason', 255)->nullable();

            // File
            $table->string('Attachment', 255)->nullable();

            $table->timestamps();

            // Indexes
            $table->index('Type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tb_requisition');
    }
};
