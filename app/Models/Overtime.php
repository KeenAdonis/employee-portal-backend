<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Overtime extends Model
{
    protected $table = 'tb_overtime_record';

    const STATUS_PENDING = 'Pending';
    const STATUS_PRE_APPROVED = 'Pre-Approved';
    const STATUS_APPROVED = 'Approved';
    const STATUS_REJECTED = 'Rejected';

    protected $fillable = [
        'RequestId',
        'DateFiled',
        'EmployeeNo',
        'EmployeeName',
        'OvertimeDate',
        'TimeFrom',
        'TimeTo',
        'TotalHours',
        'OvertimeReason',
        'Status',
        'ApprovedBy',
        'ApprovedDate',
        'Remarks',
        'DisapprovalReason'
    ];

    public function accomplishments()
    {
        return $this->hasMany(
            OvertimeAccomplishment::class,
            'RequestId',
            'RequestId'
        );
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'EmployeeNo', 'EmployeeNo');
    }
}