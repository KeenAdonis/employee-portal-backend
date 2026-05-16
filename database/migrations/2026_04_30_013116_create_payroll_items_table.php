<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('payroll_id')
                ->constrained('tb_payroll')
                ->cascadeOnDelete();

            $table->enum('type', ['earning', 'deduction']);
            $table->string('category')->nullable();
            $table->string('label');

            $table->decimal('amount', 12, 2)->default(0);

            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['payroll_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_items');
    }
};
