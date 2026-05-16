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
        Schema::table('tb_requisition', function (Blueprint $table) {

            $table->timestamp('LiquidatedDate')
                ->nullable()
                ->after('has_liquidation');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tb_requisition', function (Blueprint $table) {
            //
        });
    }
};
