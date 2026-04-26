<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Leave extends Model
{
    protected $table = 'tb_leave_record';

    protected $fillable = [
        'RequestId',
        'EmployeeNo',
        'EmployeeName',
        'DateFiled',
        'DateFrom',
        'DateTo',
        'TotalDays',
        'LeaveDuration',
        'LeaveType',
        'Reason',
        'Remarks',
        'Status',
        'ApprovedBy',
        'ApprovedDate',
        'DisapprovalReason',
        'Attachment'
    ];

    const STATUS_PENDING = 'Pending';
    const STATUS_APPROVED = 'Approved';
    const STATUS_REJECTED = 'Rejected';

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'EmployeeNo', 'EmployeeNo');
    }

    public function credit()
    {
        return $this->hasOne(LeaveCredit::class, 'EmployeeNo', 'EmployeeNo');
    }
}