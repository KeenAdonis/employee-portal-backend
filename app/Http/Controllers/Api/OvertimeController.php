<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Overtime;
use App\Models\OvertimeAccomplishment;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Models\Notification;
use App\Models\User;

use App\Events\NotificationCreated;

class OvertimeController extends Controller
{
    /* =========================
       GET /api/overtime
    ========================= */
    public function index(Request $request)
    {
        try {

            $user = auth()->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            $query = Overtime::with([
                'employee:EmployeeNo,Position,ProfileImage',
                'accomplishments:RequestId,Task,Category,TaskStatus'
            ]);

            /* =========================
               🔐 ROLE-BASED FILTER
            ========================= */
            if ($user->role === 'employee') {

                $query->where('EmployeeNo', $user->employee_no);
            }

            // adminhr → full access

            /* =========================
               🔍 SEARCH
            ========================= */
            if ($request->filled('search')) {

                $search = $request->search;

                $query->where(function ($q) use ($search) {

                    $q->where('EmployeeName', 'like', "%{$search}%")
                        ->orWhere('EmployeeNo', 'like', "%{$search}%")
                        ->orWhere('RequestId', 'like', "%{$search}%");
                });
            }

            /* =========================
               📊 STATUS FILTER
            ========================= */
            if ($request->filled('status')) {

                $statuses = explode(',', $request->status);

                $query->whereIn('Status', $statuses);
            }

            /* =========================
               📅 DATE FILTER
            ========================= */
            if ($request->filled('from') && $request->filled('to')) {

                $query->whereBetween('OvertimeDate', [
                    $request->from,
                    $request->to
                ]);
            }

            /* =========================
               📄 PAGINATION
            ========================= */
            $overtime = $query
                ->orderBy('created_at', 'desc')
                ->paginate($request->per_page ?? 10);

            return response()->json([
                'success' => true,
                'data' => $overtime
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch overtime',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {

            $user = auth()->user();

            if (!$user) {

                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            /* =========================
               VALIDATION
            ========================= */
            $request->validate([
                'OvertimeDate' => 'required|date',
                'TimeFrom' => 'required',
                'TimeTo' => 'required',
                'TotalHours' => 'required|numeric|min:0.5',
                'OvertimeReason' => 'required|string|max:2000',
            ]);

            /* =========================
               CREATE OVERTIME
            ========================= */
            $overtime = Overtime::create([

                'RequestId' => uniqid('OT-'),

                'DateFiled' => now(),

                // GET FROM AUTH USER
                'EmployeeNo' => $user->employee_no,

                'EmployeeName' => $user->name,

                'OvertimeDate' => $request->OvertimeDate,

                'TimeFrom' => $request->TimeFrom,

                'TimeTo' => $request->TimeTo,

                'TotalHours' => $request->TotalHours,

                'OvertimeReason' => $request->OvertimeReason,

                'Status' => Overtime::STATUS_PENDING,
            ]);


            /*
            |--------------------------------------------------------------------------
            | NOTIFY ADMIN HR
            |--------------------------------------------------------------------------
            */
            $adminUsers = User::query()
                ->where('role', 'adminhr')
                ->get();

            foreach ($adminUsers as $admin) {

                $notification = Notification::create([
                    'user_id' => $admin->id,

                    'type' => 'overtime',

                    'title' => 'New Overtime Request',

                    'message' =>
                        "{$user->name} submitted an overtime request.",

                    'related_type' => 'overtime',

                    'related_id' => $overtime->id,

                    'action_url' => '/dashboard/adminhr/overtime-list',
                ]);

                event(
                    new NotificationCreated(
                        $notification
                    )
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Overtime request submitted successfully.',
                'data' => $overtime
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit overtime',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function saveAccomplishments(Request $request, $id)
    {
        try {

            $overtime = Overtime::findOrFail($id);

            /* =========================
               VALIDATION
            ========================= */
            $request->validate([
                'accomplishments' => 'required|array|min:1',

                'accomplishments.*.Task' =>
                    'required|string|max:255',

                'accomplishments.*.Category' =>
                    'required|string|max:100',

                'accomplishments.*.TaskStatus' =>
                    'required|string|max:50',
            ]);

            /* =========================
               ONLY PRE-APPROVED
            ========================= */
            if (
                $overtime->Status !==
                Overtime::STATUS_PRE_APPROVED
            ) {

                return response()->json([
                    'success' => false,
                    'message' =>
                        'Only pre-approved overtime can add accomplishments.'
                ], 422);
            }

            /* =========================
               SAVE TASKS
            ========================= */
            foreach (
                $request->accomplishments
                as $item
            ) {

                OvertimeAccomplishment::create([

                    'RequestId' =>
                        $overtime->RequestId,

                    'Task' =>
                        $item['Task'],

                    'Category' =>
                        $item['Category'],

                    'TaskStatus' =>
                        $item['TaskStatus'],

                    'DateSubmitted' =>
                        now()->toDateString(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' =>
                    'Accomplishments submitted successfully.'
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' =>
                    'Failed to save accomplishments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function export(Request $request): StreamedResponse
    {
        $request->validate([
            'from' => 'required|date',
            'to' => 'required|date',
        ]);

        $from = $request->from;
        $to = $request->to;
        $type = $request->type ?? 'detailed';

        // STATUS FILTER (from tabs)
        $statuses = $request->status
            ? explode(',', $request->status)
            : [Overtime::STATUS_APPROVED];

        $query = Overtime::whereBetween('OvertimeDate', [$from, $to])
            ->whereIn('Status', $statuses);

        $filename = "overtime_{$type}_{$from}_to_{$to}.csv";

        return response()->stream(function () use ($query, $type) {

            $file = fopen('php://output', 'w');

            // Excel UTF-8 fix
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            if ($type === 'summary') {

                fputcsv($file, [
                    'Employee No',
                    'Employee Name',
                    'Total OT Hours'
                ]);

                $data = $query
                    ->selectRaw("
                    EmployeeNo,
                    EmployeeName,
                    SUM(CAST(TotalHours AS DECIMAL(5,2))) as TotalHours
                ")
                    ->groupBy('EmployeeNo', 'EmployeeName')
                    ->orderBy('EmployeeName')
                    ->get();

                $grandTotal = 0;

                foreach ($data as $row) {
                    $hours = (float) $row->TotalHours;
                    $grandTotal += $hours;

                    fputcsv($file, [
                        $row->EmployeeNo,
                        $row->EmployeeName,
                        number_format($hours, 2)
                    ]);
                }

                fputcsv($file, ['', 'TOTAL', number_format($grandTotal, 2)]);

            } else {

                fputcsv($file, [
                    'Request ID',
                    'Employee No',
                    'Employee Name',
                    'OT Date',
                    'Time From',
                    'Time To',
                    'Hours'
                ]);

                $data = $query->orderBy('OvertimeDate', 'desc')->get();

                foreach ($data as $row) {
                    fputcsv($file, [
                        $row->RequestId,
                        $row->EmployeeNo,
                        $row->EmployeeName,
                        $row->OvertimeDate,
                        $row->TimeFrom,
                        $row->TimeTo,
                        $row->TotalHours
                    ]);
                }
            }

            fclose($file);

        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename={$filename}",
        ]);
    }

    public function preApprove($id)
    {
        $overtime = Overtime::findOrFail($id);

        $overtime->update([
            'Status' => Overtime::STATUS_PRE_APPROVED,
        ]);

        $employeeUser = User::query()
            ->where('employee_no', $overtime->EmployeeNo)
            ->first();

        if ($employeeUser) {

            $notification = Notification::create([
                'user_id' => $employeeUser->id,

                'type' => 'overtime',

                'title' => 'Overtime Pre-Approved',

                'message' =>
                    'Your overtime request has been pre-approved.',

                'related_type' => 'overtime',

                'related_id' => $overtime->id,

                'action_url' => '/dashboard/employee/overtime',
            ]);

            event(
                new NotificationCreated(
                    $notification
                )
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Overtime pre-approved'
        ]);
    }

    /* =========================
       APPROVE
    ========================= */
    public function approve($id)
    {
        $overtime = Overtime::findOrFail($id);

        $overtime->update([
            'Status' => Overtime::STATUS_APPROVED,
            'ApprovedBy' => auth()->user()->name ?? 'Admin',
            'ApprovedDate' => now()
        ]);

        $employeeUser = User::query()
            ->where('employee_no', $overtime->EmployeeNo)
            ->first();

        if ($employeeUser) {

            $notification = Notification::create([
                'user_id' => $employeeUser->id,

                'type' => 'overtime',

                'title' => 'Overtime Approved',

                'message' =>
                    'Your overtime request has been approved.',

                'related_type' => 'overtime',

                'related_id' => $overtime->id,

                'action_url' => '/dashboard/employee/overtime',
            ]);

            event(
                new NotificationCreated(
                    $notification
                )
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Overtime approved'
        ]);
    }

    public function reject(Request $request, $id)
    {
        $overtime = Overtime::findOrFail($id);

        $overtime->update([
            'Status' => Overtime::STATUS_REJECTED,
            'DisapprovalReason' => $request->reason,
            'ApprovedBy' => auth()->user()->name ?? 'Admin',
            'ApprovedDate' => now()
        ]);

        $employeeUser = User::query()
            ->where('employee_no', $overtime->EmployeeNo)
            ->first();

        if ($employeeUser) {

            $notification = Notification::create([
                'user_id' => $employeeUser->id,

                'type' => 'overtime',

                'title' => 'Overtime Rejected',

                'message' =>
                    'Your overtime request was rejected.',

                'related_type' => 'overtime',

                'related_id' => $overtime->id,

                'action_url' => '/dashboard/employee/overtime',
            ]);

            event(
                new NotificationCreated(
                    $notification
                )
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Overtime rejected'
        ]);
    }
}