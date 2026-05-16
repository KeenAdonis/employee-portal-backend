<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('secure_documents', function (Blueprint $table) {
            $table->id();

            /* =========================
               RECIPIENT INFO
            ========================= */
            $table->string('employee_name');
            $table->string('email');

            /* =========================
               FILE INFO
            ========================= */
            $table->string('file_name');
            $table->string('file_path'); // encrypted file

            /* =========================
               SECURITY
            ========================= */
            $table->text('password_encrypted'); // via Crypt::encryptString()

            /* =========================
               WORKFLOW STATUS
            ========================= */
            $table->enum('status', [
                'draft',
                'queued',
                'sent',
                'failed'
            ])->default('draft');

            /* =========================
               TRACKING / AUDIT
            ========================= */
            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();

            /* =========================
               ERROR HANDLING
            ========================= */
            $table->text('error_message')->nullable();

            /* =========================
               OPTIONAL (GOOD TO HAVE)
            ========================= */
            $table->integer('resend_count')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secure_documents');
    }
};
