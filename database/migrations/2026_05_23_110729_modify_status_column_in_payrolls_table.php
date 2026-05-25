<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tb_payroll', function (Blueprint $table) {
            $table->string('status')
                ->default('Completed')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('tb_payroll', function (Blueprint $table) {
            $table->enum('status', ['Draft', 'Approved', 'Paid'])
                ->default('Draft')
                ->change();
        });
    }
};


