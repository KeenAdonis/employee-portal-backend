<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('secure_document_logs', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('document_id')->nullable();
            $table->string('action'); // upload, send, resend, failed
            $table->string('status'); // success, failed
            $table->text('message')->nullable();

            $table->string('email')->nullable(); // para searchable agad
            $table->string('employee_name')->nullable();

            $table->unsignedBigInteger('user_id')->nullable();

            $table->timestamps();

            $table->index('document_id');
            $table->index('action');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secure_document_logs');
    }
};