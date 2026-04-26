<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveCredit extends Model
{
    protected $table = 'tb_leave_credit';

    protected $fillable = [
        'EmployeeNo',

        // VL
        'VLCredits',
        'VLBalance',
        'VLFiled',

        // SL
        'SLCredits',
        'SLBalance',
        'SLFiled',

        // EL
        'ELCredits',
        'ELBalance',
        'ELFiled',

        // ML
        'MLCredits',
        'MLBalance',
        'MLFiled',

        // PL
        'PLCredits',
        'PLBalance',
        'PLFiled',

        // BL
        'BLCredits',
        'BLBalance',
        'BLFiled',

        // BDL
        'BDLCredits',
        'BDLBalance',
        'BDLFiled',

        // OL
        'OLCredits',
        'OLBalance',
        'OLFiled',
    ];

    public $timestamps = true;

    /**
     * Optional relationship
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'EmployeeNo', 'EmployeeNo');
    }
}