<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class DailyTimeRecord extends Model
{
    use HasFactory;

    protected $table = 'tb_daily_time_records';

    protected $primaryKey = 'id';

    protected $fillable = [

        /*
        |--------------------------------------------------------------------------
        | EMPLOYEE
        |--------------------------------------------------------------------------
        */

        'employee_id',

        /*
        |--------------------------------------------------------------------------
        | DATE
        |--------------------------------------------------------------------------
        */

        'date',

        /*
        |--------------------------------------------------------------------------
        | TIME LOGS
        |--------------------------------------------------------------------------
        */

        'time_in',
        'break_out',
        'break_in',
        'time_out',

        /*
        |--------------------------------------------------------------------------
        | COMPUTED HOURS
        |--------------------------------------------------------------------------
        */

        'total_work_hours',
        'total_break_hours',
        'overtime_hours',

        /*
        |--------------------------------------------------------------------------
        | ATTENDANCE METRICS
        |--------------------------------------------------------------------------
        */

        'late_minutes',
        'undertime_minutes',

        /*
        |--------------------------------------------------------------------------
        | FLAGS
        |--------------------------------------------------------------------------
        */

        'is_rest_day',
        'is_holiday',

        /*
        |--------------------------------------------------------------------------
        | SOURCE
        |--------------------------------------------------------------------------
        */

        'source_type',

        /*
        |--------------------------------------------------------------------------
        | STATUS
        |--------------------------------------------------------------------------
        */

        'status',
        'remarks',

        /*
        |--------------------------------------------------------------------------
        | APPROVAL
        |--------------------------------------------------------------------------
        */

        'approved_by',
        'approved_at',
    ];

    protected $casts = [

        'date' => 'date',

        'approved_at' => 'datetime',

        'is_rest_day' => 'boolean',

        'is_holiday' => 'boolean',

        'total_work_hours' => 'decimal:2',

        'total_break_hours' => 'decimal:2',

        'overtime_hours' => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    public function employee()
    {
        return $this->belongsTo(
            Employee::class,
            'employee_id',
            'employee_id'
        );
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
    |--------------------------------------------------------------------------
    */

    public function getFormattedDateAttribute()
    {
        return Carbon::parse($this->date)
            ->format('F d, Y');
    }

    public function getFormattedTimeInAttribute()
    {
        return $this->formatTime($this->time_in);
    }

    public function getFormattedBreakOutAttribute()
    {
        return $this->formatTime($this->break_out);
    }

    public function getFormattedBreakInAttribute()
    {
        return $this->formatTime($this->break_in);
    }

    public function getFormattedTimeOutAttribute()
    {
        return $this->formatTime($this->time_out);
    }

    /*
    |--------------------------------------------------------------------------
    | HELPER METHODS
    |--------------------------------------------------------------------------
    */

    private function formatTime($time)
    {
        if (!$time) {
            return null;
        }

        return Carbon::parse($time)
            ->format('h:i A');
    }

    /*
    |--------------------------------------------------------------------------
    | BUSINESS LOGIC
    |--------------------------------------------------------------------------
    */

    public function calculateHours()
    {
        if (
            !$this->time_in ||
            !$this->time_out
        ) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | FORMAT DATE
        |--------------------------------------------------------------------------
        */

        $date = $this->date instanceof Carbon
            ? $this->date->format('Y-m-d')
            : Carbon::parse($this->date)->format('Y-m-d');

        /*
        |--------------------------------------------------------------------------
        | PARSE TIME
        |--------------------------------------------------------------------------
        */

        $timeIn = Carbon::parse(
            $date . ' ' . $this->time_in
        );

        $timeOut = Carbon::parse(
            $date . ' ' . $this->time_out
        );

        /*
        |--------------------------------------------------------------------------
        | TOTAL WORK MINUTES
        |--------------------------------------------------------------------------
        */

        $totalMinutes =
            $timeIn->diffInMinutes($timeOut);

        /*
        |--------------------------------------------------------------------------
        | BREAK DEDUCTION
        |--------------------------------------------------------------------------
        */

        $breakMinutes = 0;

        if (
            $this->break_out &&
            $this->break_in
        ) {

            $breakOut = Carbon::parse(
                $date . ' ' . $this->break_out
            );

            $breakIn = Carbon::parse(
                $date . ' ' . $this->break_in
            );

            $breakMinutes =
                $breakOut->diffInMinutes($breakIn);
        }

        /*
        |--------------------------------------------------------------------------
        | NET WORK MINUTES
        |--------------------------------------------------------------------------
        */

        $netMinutes =
            $totalMinutes - $breakMinutes;

        /*
        |--------------------------------------------------------------------------
        | STORE COMPUTED VALUES
        |--------------------------------------------------------------------------
        */

        $this->total_break_hours = round(
            $breakMinutes / 60,
            2
        );

        $this->total_work_hours = round(
            $netMinutes / 60,
            2
        );

        /*
        |--------------------------------------------------------------------------
        | OVERTIME
        |--------------------------------------------------------------------------
        */

        $overtime = max(
            0,
            ($netMinutes / 60) - 8
        );

        $this->overtime_hours = round(
            $overtime,
            2
        );
    }
}