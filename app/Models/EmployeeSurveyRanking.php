<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EmployeeSurveyRanking extends Model
{
    use HasFactory;

    protected $table = 'tb_employee_survey_rankings';

    protected $fillable = [
        'employee_survey_id',
        'ranked_employee_id',
        'rank_position',
        'score',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    public function survey()
    {
        return $this->belongsTo(
            EmployeeSurvey::class,
            'employee_survey_id',
            'id'
        );
    }

    public function rankedEmployee()
    {
        return $this->belongsTo(
            Employee::class,
            'ranked_employee_id',
            'employee_id'
        );
    }
}