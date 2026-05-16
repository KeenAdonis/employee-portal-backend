<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tb_employee_loans', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('employee_id');
            $table->foreign('employee_id')
                ->references('employee_id')
                ->on('tb_employee_list')
                ->onDelete('cascade');

            $table->string('reference_no')->unique();

            $table->string('loan_type');

            $table->decimal('principal_amount', 12, 2)->default(0);
            $table->decimal('interest_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);

            $table->integer('terms'); // months

            $table->decimal('monthly_amortization', 12, 2)->default(0);
            $table->decimal('balance', 12, 2)->default(0);

            $table->enum('cutoff_type', ['15', '30', 'both'])->default('both');

            $table->date('start_date')->nullable();

            $table->enum('status', ['active', 'completed'])->default('active');

            $table->text('remarks')->nullable();

            $table->timestamps();

            $table->index(['employee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_employee_loans');
    }
};
