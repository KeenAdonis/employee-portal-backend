<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::create('tb_travel_liquidations', function (Blueprint $table) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | TRAVEL REQUEST
            |--------------------------------------------------------------------------
            */
            $table->foreignId('travel_request_id')
                ->constrained('tb_travel_requests')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | EXPENSES
            |--------------------------------------------------------------------------
            */
            $table->decimal('total_mileage', 10, 2)
                ->default(0);

            $table->decimal('fuel_cost', 10, 2)
                ->default(0);

            $table->decimal('toll_fee', 10, 2)
                ->default(0);

            $table->decimal('parking_fee', 10, 2)
                ->default(0);

            $table->decimal('other_expenses', 10, 2)
                ->default(0);

            $table->decimal('total_cost', 10, 2)
                ->default(0);

            /*
            |--------------------------------------------------------------------------
            | REMARKS
            |--------------------------------------------------------------------------
            */
            $table->text('remarks')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | STATUS
            |--------------------------------------------------------------------------
            */
            $table->enum('status', [
                'draft',
                'submitted',
                'approved',
                'rejected'
            ])->default('draft');

            /*
            |--------------------------------------------------------------------------
            | SUBMISSION
            |--------------------------------------------------------------------------
            */
            $table->timestamp('submitted_at')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | APPROVAL
            |--------------------------------------------------------------------------
            */
            $table->unsignedBigInteger('approved_by')
                ->nullable();

            $table->foreign('approved_by')
                ->references('employee_id')
                ->on('tb_employee_list')
                ->nullOnDelete();

            $table->timestamp('approved_at')
                ->nullable();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | INDEXES
            |--------------------------------------------------------------------------
            */
            $table->index('travel_request_id');

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_travel_liquidations');
    }
};