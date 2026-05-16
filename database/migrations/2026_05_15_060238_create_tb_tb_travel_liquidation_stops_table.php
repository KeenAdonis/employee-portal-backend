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
        Schema::create('tb_travel_liquidation_stops', function (Blueprint $table) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | LIQUIDATION
            |--------------------------------------------------------------------------
            */
            $table->foreignId('travel_liquidation_id')
                ->constrained('tb_travel_liquidations')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | ROUTE
            |--------------------------------------------------------------------------
            */
            $table->unsignedInteger('sequence_no')
                ->default(1);

            $table->string('from_location');

            $table->string('to_location');

            /*
            |--------------------------------------------------------------------------
            | ODOMETER
            |--------------------------------------------------------------------------
            */
            $table->decimal('odometer_start', 10, 2);

            $table->decimal('odometer_end', 10, 2);

            /*
            |--------------------------------------------------------------------------
            | COMPUTED MILEAGE
            |--------------------------------------------------------------------------
            */
            $table->decimal('mileage', 10, 2);

            /*
            |--------------------------------------------------------------------------
            | OPTIONAL NOTES
            |--------------------------------------------------------------------------
            */
            $table->text('remarks')
                ->nullable();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | INDEXES
            |--------------------------------------------------------------------------
            */
            $table->index('travel_liquidation_id');

            $table->index(
                ['travel_liquidation_id', 'sequence_no'],
                'travel_liq_stop_seq_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tb_travel_liquidation_stops');
    }
};