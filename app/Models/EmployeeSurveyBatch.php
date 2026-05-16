<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeSurveyBatch extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'tb_employee_survey_batches';

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'description',
        'company',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function surveys()
    {
        return $this->hasMany(
            EmployeeSurvey::class,
            'batch_id',
            'id'
        );
    }
}