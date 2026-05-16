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
        Schema::create('tb_travel_logs', function (Blueprint $table) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | REFERENCES
            |--------------------------------------------------------------------------
            */
            $table->foreignId('travel_request_id')
                ->nullable()
                ->constrained('tb_travel_requests')
                ->cascadeOnDelete();

            $table->foreignId('travel_liquidation_id')
                ->nullable()
                ->constrained('tb_travel_liquidations')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | ACTION
            |--------------------------------------------------------------------------
            */
            $table->string('action');

            $table->text('description')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | USER
            |--------------------------------------------------------------------------
            */
            $table->unsignedBigInteger('performed_by')
                ->nullable();

            $table->foreign('performed_by')
                ->references('employee_id')
                ->on('tb_employee_list')
                ->nullOnDelete();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | INDEXES
            |--------------------------------------------------------------------------
            */
            $table->index('travel_request_id');

            $table->index('travel_liquidation_id');

            $table->index('performed_by');

            $table->index('action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tb_travel_logs');
    }
};
