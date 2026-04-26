<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tb_requisition_attachments', function (Blueprint $table) {
            $table->id();

            // Link to requisition
            $table->string('RequestId')->index();

            // File details
            $table->string('FileName'); // original filename
            $table->string('FilePath'); // storage path
            $table->string('FileType')->nullable(); // mime type
            $table->unsignedBigInteger('FileSize')->nullable(); // bytes (optional)

            $table->timestamps();

            // Optional (future-proof)
            // $table->foreign('RequestId')
            //       ->references('RequestId')
            //       ->on('tb_requisition')
            //       ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_requisition_attachments');
    }
};