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
        Schema::create('tb_liquidations_logs', function (Blueprint $table) {
            $table->id();

            $table->string('liquidation_id');
            $table->string('action'); // Submitted, Checked, Approved, Rejected
            $table->string('performed_by');

            $table->text('remarks')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tb_liquidations_logs');
    }
};
