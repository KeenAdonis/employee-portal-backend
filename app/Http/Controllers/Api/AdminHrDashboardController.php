<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Models\Employee;
use App\Models\Leave;
use App\Models\Overtime;
use App\Models\Notification;

use Illuminate\Support\Facades\DB;

class AdminHrDashboardController extends Controller
{
    public function index()
    {
        /*
        |--------------------------------------------------------------------------
        | TOTAL ACTIVE EMPLOYEES
        |--------------------------------------------------------------------------
        */
        $totalEmployees = Employee::where(
            'Status',
            'ACTIVE'
        )->count();

        /*
        |--------------------------------------------------------------------------
        | EMPLOYEES PER COMPANY
        |--------------------------------------------------------------------------
        */
        $employeesPerCompany = Employee::select(
            'Company',
            DB::raw('COUNT(*) as total')
        )
            ->where('Status', 'ACTIVE')
            ->groupBy('Company')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | UPCOMING BIRTHDAYS
        |--------------------------------------------------------------------------
        */
        $upcomingBirthdays = Employee::where(
            'Status',
            'ACTIVE'
        )
            ->whereMonth('Birthday', now()->month)
            ->orderByRaw('DAY(Birthday)')
            ->take(5)
            ->get([
                'EmployeeNo',
                'FirstName',
                'LastName',
                'Birthday',
                'Department',
            ]);

        /*
        |--------------------------------------------------------------------------
        | APPROVED LEAVES TODAY
        |--------------------------------------------------------------------------
        */
        $approvedLeaves = Leave::where(
            'Status',
            Leave::STATUS_APPROVED
        )
            ->whereDate('DateFrom', '<=', today())
            ->whereDate('DateTo', '>=', today())
            ->count();

        /*
        |--------------------------------------------------------------------------
        | RECENT APPROVED LEAVES TODAY
        |--------------------------------------------------------------------------
        */
        $recentLeaves = Leave::where(
            'Status',
            Leave::STATUS_APPROVED
        )
            ->whereDate('DateFrom', '<=', today())
            ->whereDate('DateTo', '>=', today())
            ->latest()
            ->take(5)
            ->get();

        /*
        |--------------------------------------------------------------------------
        | APPROVED OVERTIME
        |--------------------------------------------------------------------------
        */
        $approvedOvertime = Overtime::where(
            'Status',
            Overtime::STATUS_APPROVED
        )
            ->whereDate(
                'OvertimeDate',
                today()
            )
            ->count();

        /*
        |--------------------------------------------------------------------------
        | RECENT APPROVED OVERTIME TODAY
        |--------------------------------------------------------------------------
        */
        $recentOvertime = Overtime::where(
            'Status',
            Overtime::STATUS_APPROVED
        )
            ->whereDate(
                'OvertimeDate',
                today()
            )
            ->latest()
            ->take(5)
            ->get();

        /*
        |--------------------------------------------------------------------------
        | UPCOMING ANNIVERSARIES
        |--------------------------------------------------------------------------
        */
        $upcomingAnniversaries = Employee::where(
            'Status',
            'ACTIVE'
        )
            ->whereMonth('DateHired', now()->month)
            ->orderByRaw('DAY(DateHired)')
            ->take(5)
            ->get([
                'EmployeeNo',
                'FirstName',
                'LastName',
                'DateHired',
                'Department',
            ]);

        /*
        |--------------------------------------------------------------------------
        | RECENT ORGANIZATION ACTIVITIES
        |--------------------------------------------------------------------------
        */
        $recentActivities = Notification::query()

            ->whereIn('type', [
                'leave',
                'overtime',
                'requisition',
                'liquidation',
                'loan',
                'payroll',
            ])

            ->latest()

            ->take(10)

            ->get();

        /*
        |--------------------------------------------------------------------------
        | RESPONSE
        |--------------------------------------------------------------------------
        */
        return response()->json([

            'success' => true,

            'data' => [

                'total_employees' => $totalEmployees,

                'employees_per_company' => $employeesPerCompany,

                'upcoming_birthdays' => $upcomingBirthdays,

                'approved_leaves' => $approvedLeaves,

                'recent_leaves' => $recentLeaves,

                'approved_overtime' => $approvedOvertime,

                'recent_overtime' => $recentOvertime,

                'upcoming_anniversaries' => $upcomingAnniversaries,

                'recent_activities' => $recentActivities,

            ]

        ]);
    }
}