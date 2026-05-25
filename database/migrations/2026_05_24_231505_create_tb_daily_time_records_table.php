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
        Schema::create('tb_daily_time_records', function (Blueprint $table) {

            $table->id();

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
            | DTR DATE
            |--------------------------------------------------------------------------
            */

            $table->date('date');

            /*
            |--------------------------------------------------------------------------
            | TIME LOGS
            |--------------------------------------------------------------------------
            */

            $table->time('time_in')->nullable();

            $table->time('break_out')->nullable();

            $table->time('break_in')->nullable();

            $table->time('time_out')->nullable();

            /*
            |--------------------------------------------------------------------------
            | COMPUTED HOURS
            |--------------------------------------------------------------------------
            */

            // Total worked hours excluding break
            $table->decimal('total_work_hours', 8, 2)
                ->default(0);

            // Total break duration
            $table->decimal('total_break_hours', 8, 2)
                ->default(0);

            // Overtime hours
            $table->decimal('overtime_hours', 8, 2)
                ->default(0);

            /*
            |--------------------------------------------------------------------------
            | ATTENDANCE METRICS
            |--------------------------------------------------------------------------
            */

            $table->integer('late_minutes')
                ->default(0);

            $table->integer('undertime_minutes')
                ->default(0);

            /*
            |--------------------------------------------------------------------------
            | OPTIONAL FLAGS
            |--------------------------------------------------------------------------
            */

            $table->boolean('is_rest_day')
                ->default(false);

            $table->boolean('is_holiday')
                ->default(false);

            /*
            |--------------------------------------------------------------------------
            | SOURCE
            |--------------------------------------------------------------------------
            */

            // manual, biometric, imported, mobile
            $table->string('source_type')
                ->default('manual');

            /*
            |--------------------------------------------------------------------------
            | STATUS
            |--------------------------------------------------------------------------
            */

            // draft, pending, approved, rejected
            $table->enum('status', [
                'draft',
                'pending',
                'approved',
                'rejected'
            ])->default('draft');

            $table->text('remarks')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | APPROVAL
            |--------------------------------------------------------------------------
            */

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('approved_at')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | UNIQUE DTR PER DAY
            |--------------------------------------------------------------------------
            */

            $table->unique([
                'employee_id',
                'date'
            ]);

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | INDEXES
            |--------------------------------------------------------------------------
            */

            $table->index('date');

            $table->index('status');

            $table->index([
                'employee_id',
                'status'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tb_daily_time_records');
    }
};