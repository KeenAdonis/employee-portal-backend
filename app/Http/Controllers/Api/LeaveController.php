<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Leave;
use App\Models\LeaveCredit;
use App\Models\Notification;
use App\Models\User;
use App\Events\NotificationCreated;
use App\Services\LeavePolicyService;
use Illuminate\Support\Facades\DB;

class LeaveController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        $query = Leave::with([
            'employee:EmployeeNo,Position,ProfileImage',
            'credit'
        ]);

        /* =========================
           🔐 ROLE-BASED FILTER
        ========================= */
        if ($user->role === 'employee') {

            $query->where('EmployeeNo', $user->employee_no);
        }

        // adminhr → full access

        /* =========================
           📊 STATUS FILTER
        ========================= */
        if (
            $request->filled('status') &&
            $request->status !== 'all'
        ) {

            $statuses = explode(',', $request->status);

            $query->whereIn('Status', $statuses);
        }

        /* =========================
           🔍 SEARCH FILTER
        ========================= */
        if ($request->filled('search')) {

            $search = $request->search;

            $query->where(function ($q) use ($search) {

                $q->where('EmployeeName', 'like', "%{$search}%")
                    ->orWhere('RequestId', 'like', "%{$search}%")
                    ->orWhere('LeaveType', 'like', "%{$search}%")
                    ->orWhere('Reason', 'like', "%{$search}%")
                    ->orWhere('Status', 'like', "%{$search}%");
            });
        }

        /* =========================
           📅 DATE FILTER
        ========================= */
        if ($request->filled('from') && $request->filled('to')) {

            $query->whereBetween('DateFiled', [
                $request->from,
                $request->to
            ]);
        }

        /* =========================
           📄 PAGINATION
        ========================= */
        $leave = $query
            ->orderBy('DateFiled', 'desc')
            ->paginate($request->per_page ?? 10);

        /* =========================
           📎 ATTACHMENT URL
        ========================= */
        $leave->getCollection()->transform(function ($item) {

            if ($item->Attachment) {

                $item->Attachment = asset('storage/' . $item->Attachment);
            }

            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $leave
        ]);
    }

    public function store(Request $request)
    {
        try {

            /* =========================
               ✅ VALIDATION
            ========================= */
            $request->validate([
                'DateFrom' => 'required|date',
                'DateTo' => 'required|date|after_or_equal:DateFrom',
                'TotalDays' => 'required|numeric|min:0.5',
                'LeaveType' => 'required|string',
                'LeaveDuration' => 'required|string',
                'Reason' => 'required|string',

                'Attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048'
            ]);

            $attachmentPath = null;

            /* =========================
               📎 FILE UPLOAD
            ========================= */
            if ($request->hasFile('Attachment')) {

                $file = $request->file('Attachment');

                $attachmentPath = $file->store('leave', 'public');
            }

            /* =========================
               🧠 LEAVE POLICY VALIDATION
            ========================= */
            if ($request->LeaveType === 'Vacation Leave') {

                $validation = LeavePolicyService::validateVacationLeave(
                    $request->DateFrom,
                    $request->TotalDays
                );

                if (!$validation['valid']) {

                    return response()->json([
                        'success' => false,
                        'message' => $validation['message']
                    ], 422);
                }
            }

            if ($request->LeaveType === 'Sick Leave') {

                $validation = LeavePolicyService::validateSickLeaveAttachment(
                    $request->TotalDays,
                    $attachmentPath
                );

                if (!$validation['valid']) {

                    return response()->json([
                        'success' => false,
                        'message' => $validation['message']
                    ], 422);
                }
            }

            /* =========================
               💳 CHECK LEAVE BALANCE
            ========================= */
            $map = $this->getLeaveMapping(
                $request->LeaveType
            );

            if ($map && $map['balance']) {

                $balanceField = $map['balance'];

                $credit = LeaveCredit::where(
                    'EmployeeNo',
                    auth()->user()->employee_no
                )->first();

                $availableBalance =
                    (float) ($credit?->$balanceField ?? 0);

                $requestedDays =
                    (float) $request->TotalDays;

                if ($requestedDays > $availableBalance) {

                    return response()->json([
                        'success' => false,
                        'message' =>
                            "Insufficient leave balance. Only {$availableBalance} day(s) available."
                    ], 422);
                }
            }

            /* =========================
               💾 CREATE LEAVE
            ========================= */
            $leave = Leave::create([
                'RequestId' => uniqid('LV-'),

                // ✅ SECURE USER SOURCE
                'EmployeeNo' => auth()->user()->employee_no,
                'EmployeeName' => auth()->user()->name,

                'DateFiled' => now(),
                'DateFrom' => $request->DateFrom,
                'DateTo' => $request->DateTo,
                'TotalDays' => $request->TotalDays,
                'LeaveType' => $request->LeaveType,
                'LeaveDuration' => $request->LeaveDuration,
                'Reason' => $request->Reason,
                'Status' => Leave::STATUS_PENDING,
                'Attachment' => $attachmentPath,
            ]);

            /* =========================
               NOTIFY HR
            ========================= */
            $hrUsers = User::query()
                ->where('role', 'adminhr')
                ->get();

            foreach ($hrUsers as $hr) {

                $notification = Notification::create([
                    'user_id' => $hr->id,

                    'type' => 'leave',

                    'title' => 'New Leave Request',

                    'message' =>
                        auth()->user()->name . ' submitted a leave request.',

                    'related_type' => 'leave',

                    'related_id' => $leave->id,

                    'action_url' => '/dashboard/adminhr/leave-list',
                ]);

                event(
                    new NotificationCreated(
                        $notification
                    )
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Leave submitted successfully',
                'data' => $leave
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit leave',
                'error' => $e->getMessage()
            ], 500);
        }
    }



    public function approve($id)
    {
        return DB::transaction(function () use ($id) {

            $leave = Leave::lockForUpdate()->findOrFail($id);

            // prevent double processing
            if ($leave->Status !== Leave::STATUS_PENDING) {
                return response()->json([
                    'success' => false,
                    'message' => 'Leave already processed'
                ], 400);
            }

            $map = $this->getLeaveMapping($leave->LeaveType);

            if (!$map) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid leave type'
                ], 400);
            }

            $balanceField = $map['balance'];
            $filedField = $map['filed'];
            $days = (float) $leave->TotalDays;

            // ensure credit row exists
            $credit = LeaveCredit::lockForUpdate()
                ->firstOrCreate(
                    ['EmployeeNo' => $leave->EmployeeNo],
                    []
                );

            /**
             * =========================
             * 🟡 OTHER LEAVE (OL)
             * =========================
             * - No deduction
             * - Always allowed
             */
            if ($balanceField === null) {
                LeaveCredit::where('id', $credit->id)
                    ->update([
                        $filedField => DB::raw("$filedField + {$days}")
                    ]);
            } else {
                /**
                 * =========================
                 * 🔒 NORMAL LEAVE (VL, SL, etc.)
                 * =========================
                 */
                $affected = LeaveCredit::where('id', $credit->id)
                    ->where($balanceField, '>=', $days)
                    ->update([
                        $balanceField => DB::raw("$balanceField - {$days}"),
                        $filedField => DB::raw("$filedField + {$days}"),
                    ]);

                if ($affected === 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Insufficient leave balance'
                    ], 400);
                }
            }

            // update leave status
            $leave->update([
                'Status' => Leave::STATUS_APPROVED,
                'ApprovedBy' => auth()->user()->name ?? 'Admin',
                'ApprovedDate' => now(),
            ]);

            /* =========================
               NOTIFY EMPLOYEE
            ========================= */
            $employeeUser = User::query()
                ->where('employee_no', $leave->EmployeeNo)
                ->first();

            if ($employeeUser) {

                $notification = Notification::create([
                    'user_id' => $employeeUser->id,

                    'type' => 'leave',

                    'title' => 'Leave Approved',

                    'message' =>
                        'Your leave request has been approved.',

                    'related_type' => 'leave',

                    'related_id' => $leave->id,

                    'action_url' => '/dashboard/employee/leave',
                ]);

                event(
                    new NotificationCreated(
                        $notification
                    )
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Leave approved successfully'
            ]);
        });
    }

    public function reject(Request $request, $id)
    {
        $leave = Leave::findOrFail($id);

        $leave->update([
            'Status' => Leave::STATUS_REJECTED,
            'DisapprovalReason' => $request->reason,
            'ApprovedBy' => auth()->user()->name ?? 'Admin',
            'ApprovedDate' => now()
        ]);

        /* =========================
           NOTIFY EMPLOYEE
        ========================= */
        $employeeUser = User::query()
            ->where('employee_no', $leave->EmployeeNo)
            ->first();

        if ($employeeUser) {

            $notification = Notification::create([
                'user_id' => $employeeUser->id,

                'type' => 'leave',

                'title' => 'Leave Rejected',

                'message' =>
                    'Your leave request was rejected.',

                'related_type' => 'leave',

                'related_id' => $leave->id,

                'action_url' => '/dashboard/employee/leave',
            ]);

            event(
                new NotificationCreated(
                    $notification
                )
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Leave rejected'
        ]);
    }

    /**
     * =========================
     * 🧠 LEAVE TYPE MAPPING
     * =========================
     */
    private function getLeaveMapping($type)
    {
        return match ($type) {
            'Vacation Leave' => ['balance' => 'VLBalance', 'filed' => 'VLFiled'],
            'Sick Leave' => ['balance' => 'SLBalance', 'filed' => 'SLFiled'],
            'Emergency Leave' => ['balance' => 'ELBalance', 'filed' => 'ELFiled'],
            'Maternity Leave' => ['balance' => 'MLBalance', 'filed' => 'MLFiled'],
            'Paternity Leave' => ['balance' => 'PLBalance', 'filed' => 'PLFiled'],
            'Bereavement Leave' => ['balance' => 'BLBalance', 'filed' => 'BLFiled'],
            'Birthday Leave' => ['balance' => 'BDLBalance', 'filed' => 'BDLFiled'],

            // ✅ OL (no balance)
            'Other Leave' => ['balance' => null, 'filed' => 'OLFiled'],

            default => null,
        };
    }
}