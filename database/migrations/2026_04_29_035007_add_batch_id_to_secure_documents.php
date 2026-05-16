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
        Schema::table('secure_documents', function (Blueprint $table) {
            $table->unsignedBigInteger('batch_id')->nullable()->after('id');

            $table->foreign('batch_id')
                ->references('id')
                ->on('secure_document_batches')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('secure_documents', function (Blueprint $table) {
            //
        });
    }
};
