<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $table = 'tb_employee_list';

    protected $primaryKey = 'employee_id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'Status',
        'EmployeeNo',
        'FirstName',
        'MiddleInitial',
        'LastName',
        'HomeAddress',
        'Birthday',
        'Gender',
        'CivilStatus',
        'ContactNumber',
        'EmailAddress',
        'DateHired',
        'Department',
        'Company',
        'IsSurveyExcluded',
        'CompanyStatus',
        'Position',
        'JobLevel',
        'MonthlySalary',
        'SSSNumber',
        'PhilHealthNumber',
        'PagIbigNumber',
        'TIN',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    public function evaluatedSurveys()
    {
        return $this->hasMany(
            EmployeeSurvey::class,
            'evaluator_employee_id',
            'employee_id'
        );
    }

    public function topRankedSurveys()
    {
        return $this->hasMany(
            EmployeeSurvey::class,
            'top_employee_id',
            'employee_id'
        );
    }

    public function bottomRankedSurveys()
    {
        return $this->hasMany(
            EmployeeSurvey::class,
            'bottom_employee_id',
            'employee_id'
        );
    }

    public function surveyRankings()
    {
        return $this->hasMany(
            EmployeeSurveyRanking::class,
            'ranked_employee_id',
            'employee_id'
        );
    }
}