<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("
        ALTER TABLE tb_leave_credit
        ADD CONSTRAINT chk_vl_balance CHECK (VLBalance >= 0),
        ADD CONSTRAINT chk_sl_balance CHECK (SLBalance >= 0),
        ADD CONSTRAINT chk_el_balance CHECK (ELBalance >= 0),
        ADD CONSTRAINT chk_ml_balance CHECK (MLBalance >= 0),
        ADD CONSTRAINT chk_pl_balance CHECK (PLBalance >= 0),
        ADD CONSTRAINT chk_bl_balance CHECK (BLBalance >= 0),
        ADD CONSTRAINT chk_bdl_balance CHECK (BDLBalance >= 0)
    ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("
        ALTER TABLE tb_leave_credit
        DROP CHECK chk_vl_balance,
        DROP CHECK chk_sl_balance,
        DROP CHECK chk_el_balance,
        DROP CHECK chk_ml_balance,
        DROP CHECK chk_pl_balance,
        DROP CHECK chk_bl_balance,
        DROP CHECK chk_bdl_balance
    ");
    }
};
