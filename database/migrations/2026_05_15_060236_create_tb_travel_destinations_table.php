<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::create('tb_travel_destinations', function (Blueprint $table) {

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
            | DESTINATION DETAILS
            |--------------------------------------------------------------------------
            */
            $table->unsignedInteger('sequence_no');

            $table->string('location');

            $table->text('remarks')
                ->nullable();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | INDEXES
            |--------------------------------------------------------------------------
            */
            $table->index('travel_request_id');

            $table->index([
                'travel_request_id',
                'sequence_no'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_travel_destinations');
    }
};