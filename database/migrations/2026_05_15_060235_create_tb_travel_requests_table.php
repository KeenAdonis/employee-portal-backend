<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::create('tb_travel_requests', function (Blueprint $table) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | TRAVEL REFERENCE
            |--------------------------------------------------------------------------
            */
            $table->string('travel_no')
                ->unique();

            /*
            |--------------------------------------------------------------------------
            | EMPLOYEE
            |--------------------------------------------------------------------------
            */
            $table->unsignedBigInteger('employee_id');

            $table->foreign('employee_id')
                ->references('employee_id')
                ->on('tb_employee_list')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | TRAVEL DETAILS
            |--------------------------------------------------------------------------
            */
            $table->string('destination');

            $table->text('purpose');

            $table->enum('transportation_type', [
                'company_vehicle',
                'personal_vehicle',
                'commute',
                'air_travel'
            ]);

            /*
            |--------------------------------------------------------------------------
            | PERSONAL VEHICLE DETAILS
            |--------------------------------------------------------------------------
            */
            $table->string('plate_number')
                ->nullable();

            $table->decimal('fuel_consumption', 10, 2)
                ->nullable();

            $table->enum('fuel_type', [
                'diesel',
                'premium',
                'regular'
            ])->nullable();

            /*
            |--------------------------------------------------------------------------
            | TRAVEL SCHEDULE
            |--------------------------------------------------------------------------
            */
            $table->dateTime('departure_datetime');

            $table->dateTime('return_datetime');

            $table->unsignedInteger('total_days');

            /*
            |--------------------------------------------------------------------------
            | STATUS
            |--------------------------------------------------------------------------
            */
            $table->enum('status', [
                'draft',
                'submitted',
                'approved',
                'rejected',
                'cancelled',
                'completed',
                'liquidated',
                'closed'
            ])->default('submitted');

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

            $table->text('rejection_reason')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | LIQUIDATION
            |--------------------------------------------------------------------------
            */
            $table->boolean('is_liquidated')
                ->default(false);

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | INDEXES
            |--------------------------------------------------------------------------
            */
            $table->index('employee_id');

            $table->index('status');

            $table->index([
                'departure_datetime',
                'return_datetime'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_travel_requests');
    }
};