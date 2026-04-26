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
        Schema::create('tb_requisition_logs', function (Blueprint $table) {
            $table->id();

            $table->string('RequestId', 50)->index();

            $table->string('Action', 50); // Checked, Approved, Rejected
            $table->string('PerformedBy', 100);
            $table->timestamp('PerformedAt')->useCurrent();

            $table->string('Remarks', 255)->nullable(); // optional (for rejection reason)

            $table->timestamps();

            // FK (optional but recommended)
            $table->foreign('RequestId')
                ->references('RequestId')
                ->on('tb_requisition')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tb_requisition_logs');
    }
};
