<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('secure_document_recipients', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('document_id');
            $table->string('email');

            // 🔥 per-recipient status
            $table->enum('status', ['Pending', 'Sent', 'Failed'])
                  ->default('Pending');

            $table->text('error_message')->nullable();

            $table->timestamps();

            // 🔗 foreign key
            $table->foreign('document_id')
                  ->references('id')
                  ->on('secure_documents')
                  ->onDelete('cascade');

            // ⚡ prevent duplicate emails per document
            $table->unique(['document_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secure_document_recipients');
    }
};
