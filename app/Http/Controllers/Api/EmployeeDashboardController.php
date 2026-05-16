<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Models\Employee;
use App\Models\Leave;
use App\Models\LeaveCredit;
use App\Models\Overtime;
use App\Models\Requisition;
use App\Models\Liquidation;
use App\Models\Notification;

class EmployeeDashboardController extends Controller
{
    public function index()
    {
        /*
        |--------------------------------------------------------------------------
        | AUTH USER
        |--------------------------------------------------------------------------
        */
        $user = auth()->user();

        if (!$user) {

            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        /*
        |--------------------------------------------------------------------------
        | EMPLOYEE RECORD
        |--------------------------------------------------------------------------
        */
        $employee = Employee::query()

            ->where(
                'EmployeeNo',
                $user->employee_no
            )

            ->first();

        if (!$employee) {

            return response()->json([
                'success' => false,
                'message' => 'Employee not found.',
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | DASHBOARD STATS
        |--------------------------------------------------------------------------
        */
        $pendingLeaves = Leave::query()

            ->where(
                'EmployeeNo',
                $employee->EmployeeNo
            )

            ->whereIn('Status', [
                'Pending',
                'Approved',
            ])

            ->count();

        $pendingOvertime = Overtime::query()

            ->where(
                'EmployeeNo',
                $employee->EmployeeNo
            )

            ->whereIn('Status', [
                'Pending',
                'Pre-Approved',
            ])

            ->count();

        $pendingRequisitions = Requisition::query()

            ->where(
                'EmployeeNo',
                $employee->EmployeeNo
            )

            ->whereIn('Status', [
                'Pending',
                'Checked',
            ])

            ->count();

        $pendingLiquidations = Liquidation::query()

            ->whereHas('requisition', function ($query) use ($employee) {

                $query->where(
                    'EmployeeNo',
                    $employee->EmployeeNo
                );
            })

            ->whereIn('status', [
                'Pending',
                'Checked',
            ])

            ->count();

        /*
        |--------------------------------------------------------------------------
        | RECENT NOTIFICATIONS
        |--------------------------------------------------------------------------
        */
        $recentNotifications = Notification::query()

            ->where(
                'user_id',
                $user->id
            )

            ->latest()

            ->take(5)

            ->get();

        /*
        |--------------------------------------------------------------------------
        | RECENT REQUESTS
        |--------------------------------------------------------------------------
        */
        $recentRequests = [

            'leaves' => Leave::query()

                ->where(
                    'EmployeeNo',
                    $employee->EmployeeNo
                )

                ->latest()

                ->take(5)

                ->get(),

            'overtime' => Overtime::query()

                ->where(
                    'EmployeeNo',
                    $employee->EmployeeNo
                )

                ->latest()

                ->take(5)

                ->get(),

            'requisitions' => Requisition::query()

                ->where(
                    'EmployeeNo',
                    $employee->EmployeeNo
                )

                ->latest()

                ->take(5)

                ->get(),

            'liquidations' => Liquidation::query()

                ->whereHas('requisition', function ($query) use ($employee) {

                    $query->where(
                        'EmployeeNo',
                        $employee->EmployeeNo
                    );
                })

                ->latest()

                ->take(5)

                ->get(),
        ];

        /*
        |--------------------------------------------------------------------------
        | LEAVE CREDITS
        |--------------------------------------------------------------------------
        */
        $leaveCredit = LeaveCredit::query()

            ->where(
                'EmployeeNo',
                $employee->EmployeeNo
            )

            ->first();

        /*
        |--------------------------------------------------------------------------
        | RESPONSE
        |--------------------------------------------------------------------------
        */
        return response()->json([

            'success' => true,

            'data' => [

                'employee' => [

                    'employee_no' =>
                        $employee->EmployeeNo,

                    'name' =>
                        trim(
                            $employee->FirstName .
                            ' ' .
                            $employee->LastName
                        ),

                    'department' =>
                        $employee->Department,

                    'company' =>
                        $employee->Company,

                    'birthday' =>
                        $employee->Birthday,

                    'date_hired' =>
                        $employee->DateHired,
                ],

                'leave_credits' => [

                    'vacation_leave' =>
                        $leaveCredit->VLBalance ?? 0,

                    'sick_leave' =>
                        $leaveCredit->SLBalance ?? 0,

                    'emergency_leave' =>
                        $leaveCredit->ELBalance ?? 0,

                    'maternity_leave' =>
                        $leaveCredit->MLBalance ?? 0,

                    'paternity_leave' =>
                        $leaveCredit->PLBalance ?? 0,

                    'bereavement_leave' =>
                        $leaveCredit->BLBalance ?? 0,

                    'birthday_leave' =>
                        $leaveCredit->BDLBalance ?? 0,
                ],


                'stats' => [

                    'pending_leaves' =>
                        $pendingLeaves,

                    'pending_overtime' =>
                        $pendingOvertime,

                    'pending_requisitions' =>
                        $pendingRequisitions,

                    'pending_liquidations' =>
                        $pendingLiquidations,
                ],

                'recent_notifications' =>
                    $recentNotifications,

                'recent_requests' =>
                    $recentRequests,
            ]
        ]);
    }
}