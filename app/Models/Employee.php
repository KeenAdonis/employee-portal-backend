<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $table = 'tb_employee_list';

    protected $primaryKey = 'EmployeeNo';
    public $incrementing = false;
    protected $keyType = 'string';

    public $timestamps = false; // since wala ka created_at/updated_at

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
        'CompanyStatus',
        'Position',
        'JobLevel',
        'MonthlySalary',
        'SSSNumber',
        'PhilHealthNumber',
        'PagIbigNumber',
        'TIN',
    ];
}