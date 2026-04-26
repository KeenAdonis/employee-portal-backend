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
        Schema::create('tb_requisition_particular', function (Blueprint $table) {
            $table->id();

            $table->string('RequestId', 50)->index();
            $table->string('ParticularId', 50);

            $table->string('Particulars', 150);
            $table->decimal('Amount', 12, 2);

            $table->timestamps();

            // FK (IMPORTANT 🔥)
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
        Schema::dropIfExists('tb_requisition_particular');
    }
};
