<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

use App\Models\Employee;
use App\Models\RequisitionAttachment;
use App\Models\RequisitionParticular;
use App\Models\RequisitionLog;

class Requisition extends Model
{
    protected $table = 'tb_requisition';

    protected $fillable = [
        'RequestId',
        'Type',
        'ControlNo',
        'DateFiled',
        'EmployeeNo',
        'EmployeeName',
        'Department',
        'StartDateNeeded',
        'EndDateNeeded',
        'TotalAmount',
        'Remarks',
        'Status',
        'ReceivedDate',
        'CheckedBy',
        'CheckedDate',
        'ApprovedBy',
        'ApprovedDate',
        'DisapprovalReason',
        'LiquidatedDate',
    ];

    /* =========================
       CASTS (IMPORTANT 🔥)
    ========================= */
    protected $casts = [
        'DateFiled' => 'datetime',
        'StartDateNeeded' => 'date',
        'EndDateNeeded' => 'date',
        'ReceivedDate' => 'date',
        'CheckedDate' => 'date',
        'ApprovedDate' => 'date',
        'LiquidatedDate' => 'datetime',
        'TotalAmount' => 'decimal:2',
    ];

    /* =========================
       APPENDED ATTRIBUTES
    ========================= */
    protected $appends = [
        'overdue_days',
        'overdue_progress'
    ];

    /* =========================
       STATUS CONSTANTS
    ========================= */
    const STATUS_PENDING = 'Pending';
    const STATUS_CHECKED = 'Checked';
    const STATUS_APPROVED = 'Approved';
    const STATUS_REJECTED = 'Rejected';
    const STATUS_LIQUIDATED = 'Liquidated';

    /* =========================
       RELATIONSHIPS
    ========================= */

    public function particulars()
    {
        return $this->hasMany(
            RequisitionParticular::class,
            'RequestId',
            'RequestId'
        );
    }

    public function logs()
    {
        return $this->hasMany(
            RequisitionLog::class,
            'RequestId',
            'RequestId'
        );
    }

    /* =========================
       ACCESSORS
    ========================= */

    /**
     * Overdue Days (3-day rule)
     * Example:
     * EndDate = Apr 25
     * Grace = Apr 28
     * Overdue starts = Apr 29
     */
    public function getOverdueDaysAttribute()
    {
        if (!$this->EndDateNeeded) {
            return 0;
        }

        $endDate = Carbon::parse(
            $this->EndDateNeeded
        );

        // BUSINESS DAYS ONLY
        $dueDate = $endDate
            ->copy()
            ->addWeekdays(3);

        // 🔥 freeze if liquidated
        $today = $this->LiquidatedDate
            ? Carbon::parse(
                $this->LiquidatedDate
            )->startOfDay()
            : now()->startOfDay();

        if ($today->lte($dueDate)) {
            return 0;
        }

        return $dueDate
            ->diffInWeekdays($today);
    }

    /**
     * Optional helper: check if overdue
     */
    public function getOverdueProgressAttribute()
    {
        if (!$this->EndDateNeeded) {
            return [
                'completed' => false,
                'days_passed' => 0,
                'grace' => 3,
                'overdue_days' => 0,
                'status' => 'safe',
                'overdue_start' => null,
            ];
        }

        $end = Carbon::parse($this->EndDateNeeded)->startOfDay();
        $today = $this->LiquidatedDate
            ? Carbon::parse($this->LiquidatedDate)->startOfDay()
            : now()->startOfDay();

        // FIXED 🔥
        $daysPassed = max(
            0,
            $end->diffInWeekdays($today)
        );

        $grace = 3;
        $overdueDays = max(0, $daysPassed - $grace);

        $status = 'safe';
        if ($daysPassed >= 2)
            $status = 'warning';
        if ($daysPassed > $grace)
            $status = 'overdue';

        return [
            'completed' => (bool) $this->has_liquidation,
            'days_passed' => $daysPassed,
            'grace' => $grace,
            'overdue_days' => $overdueDays,
            'status' => $status,
            'overdue_start' => $end->copy()->addWeekdays($grace + 1), // fixed also
        ];
    }

    public function employee()
    {
        return $this->belongsTo(
            Employee::class,
            'EmployeeNo',
            'EmployeeNo'
        );
    }

    public function attachments()
    {
        return $this->hasMany(
            RequisitionAttachment::class,
            'RequestId',
            'RequestId'
        );
    }
}