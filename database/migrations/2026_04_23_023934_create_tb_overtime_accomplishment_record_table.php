<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('tb_overtime_accomplishment_record', function (Blueprint $table) {
            $table->id();

            $table->string('RequestId', 50);
            $table->string('Task', 255);
            $table->string('Category', 50);
            $table->string('TaskStatus', 50);

            $table->date('DateSubmitted');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tb_overtime_accomplishment_record');
    }
};
