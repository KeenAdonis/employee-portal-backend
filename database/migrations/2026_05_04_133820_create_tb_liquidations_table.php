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
        Schema::create('tb_liquidations', function (Blueprint $table) {
            $table->id();

            $table->string('request_id'); // link sa tb_requisition

            $table->decimal('cash_advance', 12, 2); // requested amount
            $table->decimal('total_expenses', 12, 2)->default(0);

            $table->decimal('amount_reimbursement', 12, 2)->default(0);
            $table->decimal('amount_returned', 12, 2)->default(0);

            $table->enum('status', ['Pending', 'Checked', 'Approved', 'Rejected'])->default('Pending');

            $table->text('remarks')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tb_liquidations');
    }
};
