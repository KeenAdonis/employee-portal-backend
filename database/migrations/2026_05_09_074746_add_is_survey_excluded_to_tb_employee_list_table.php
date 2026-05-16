<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'tb_employee_list',
            function (Blueprint $table) {

                $table->boolean('IsSurveyExcluded')
                    ->default(false)
                    ->after('Company');

            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'tb_employee_list',
            function (Blueprint $table) {

                $table->dropColumn(
                    'is_survey_excluded'
                );

            }
        );
    }
};