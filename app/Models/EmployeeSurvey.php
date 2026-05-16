<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EmployeeSurvey extends Model
{
    use HasFactory;

    protected $table = 'tb_employee_surveys';

    protected $fillable = [
        'batch_id',
        'evaluator_employee_id',
        'top_employee_id',
        'bottom_employee_id',
        'top_reason',
        'bottom_reason',
        'submitted_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    public function batch()
    {
        return $this->belongsTo(
            EmployeeSurveyBatch::class,
            'batch_id',
            'id'
        );
    }

    public function evaluator()
    {
        return $this->belongsTo(
            Employee::class,
            'evaluator_employee_id',
            'employee_id'
        );
    }

    public function topEmployee()
    {
        return $this->belongsTo(
            Employee::class,
            'top_employee_id',
            'employee_id'
        );
    }

    public function bottomEmployee()
    {
        return $this->belongsTo(
            Employee::class,
            'bottom_employee_id',
            'employee_id'
        );
    }

    public function rankings()
    {
        return $this->hasMany(
            EmployeeSurveyRanking::class,
            'employee_survey_id',
            'id'
        );
    }
}