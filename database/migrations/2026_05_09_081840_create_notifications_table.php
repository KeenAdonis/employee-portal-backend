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
        Schema::create('tb_notifications', function (Blueprint $table) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | RECEIVER
            |--------------------------------------------------------------------------
            */
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | NOTIFICATION CONTENT
            |--------------------------------------------------------------------------
            */
            $table->string('type'); // overtime, leave, loan, requisition

            $table->string('title');

            $table->text('message')->nullable();

            /*
            |--------------------------------------------------------------------------
            | RELATED RECORD
            |--------------------------------------------------------------------------
            */
            $table->string('related_type')->nullable();
            $table->unsignedBigInteger('related_id')->nullable();

            /*
            |--------------------------------------------------------------------------
            | STATUS
            |--------------------------------------------------------------------------
            */
            $table->boolean('is_read')
                ->default(false);

            $table->timestamp('read_at')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | OPTIONAL ACTION URL
            |--------------------------------------------------------------------------
            */
            $table->string('action_url')
                ->nullable();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | INDEXES
            |--------------------------------------------------------------------------
            */
            $table->index(['user_id', 'is_read']);

            $table->index(['related_type', 'related_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tb_notifications');
    }
};