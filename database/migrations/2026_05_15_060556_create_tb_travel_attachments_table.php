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
        Schema::create('tb_travel_attachments', function (Blueprint $table) {

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
            | FILE DETAILS
            |--------------------------------------------------------------------------
            */
            $table->string('file_name');

            $table->text('file_path');

            $table->bigInteger('file_size')
                ->nullable();

            $table->string('mime_type')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | UPLOADER
            |--------------------------------------------------------------------------
            */
            $table->unsignedBigInteger('uploaded_by')
                ->nullable();

            $table->foreign('uploaded_by')
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

            $table->index('uploaded_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tb_travel_attachments');
    }
};