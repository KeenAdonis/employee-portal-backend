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
        Schema::table('tb_employee_loans_payments', function (Blueprint $table) {
            $table->foreign('loan_id')
                ->references('id')
                ->on('tb_employee_loans')
                ->onDelete('cascade');

            $table->index('loan_id');
            $table->unique(['loan_id', 'deduction_date']);
        });
    }

    public function down(): void
    {
        Schema::table('tb_employee_loans_payments', function (Blueprint $table) {
            $table->dropForeign(['loan_id']);
        });
    }
};
