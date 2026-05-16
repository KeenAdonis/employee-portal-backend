<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\Requisition;
use App\Models\RequisitionLog;
use App\Models\RequisitionAttachment;
use App\Models\RequisitionParticular;
use Illuminate\Support\Facades\Auth;
use App\Models\Employee;
use App\Models\User;
use App\Models\Notification;
use App\Events\NotificationCreated;
use Carbon\Carbon;

class RequisitionController extends Controller
{
    /* =========================
       LIST (INDEX)
    ========================= */
    public function index(Request $request)
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        $query = Requisition::with([
            'employee',
            'particulars',
            'attachments',
            'logs',
        ]);

        /* =========================
           🔐 ROLE-BASED FILTER
        ========================= */
        if ($user->role === 'employee') {
            $query->where('EmployeeNo', $user->employee_no);
        }

        // adminhr / adminaccounting → full access (no filter)

        /* =========================
           📊 STATUS FILTER
        ========================= */
        if ($request->filled('status')) {
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
                    ->orWhere('Type', 'like', "%{$search}%");
            });
        }

        /* =========================
           📅 DATE FILTER (optional)
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
        $data = $query
            ->orderBy('DateFiled', 'desc')
            ->paginate($request->per_page ?? 10);

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /* =========================
       STORE (CREATE REQUEST)
    ========================= */




    public function store(Request $request)
    {
        $validated = $request->validate([
            'Type' => 'required|string|max:50',
            'StartDateNeeded' => 'required|date',
            'EndDateNeeded' => 'required|date|after_or_equal:StartDateNeeded',
            'Remarks' => 'nullable|string',

            'particulars' => 'required|array|min:1',
            'particulars.*.Particulars' => 'required|string|max:255',
            'particulars.*.Amount' => 'required|numeric|min:0',

            'attachments.*' => 'nullable|file|max:10240',
        ]);

        /* =========================
           BUSINESS RULES
        ========================= */

        $type = $validated['Type'];

        $startDate = Carbon::parse($validated['StartDateNeeded'])->startOfDay();

        $today = now()->startOfDay();

        /* =========================
           3 DAYS AHEAD RULE
        ========================= */

        if (
            in_array($type, [
                'Cash Advance',
                'Request for Payment'
            ])
        ) {

            $minimumDate = now()
                ->addDays(3)
                ->startOfDay();

            if ($startDate->lt($minimumDate)) {

                return response()->json([
                    'message' =>
                        'This request type must be filed at least 3 days ahead.'
                ], 422);
            }
        }

        /* =========================
           WEEKEND VALIDATION
        ========================= */

        if (
            $startDate->isWeekend()
        ) {

            return response()->json([
                'message' =>
                    'Weekend requests are not allowed.'
            ], 422);
        }

        $endDate = Carbon::parse(
            $validated['EndDateNeeded']
        );

        if (
            $endDate->isWeekend()
        ) {

            return response()->json([
                'message' =>
                    'Weekend requests are not allowed.'
            ], 422);
        }

        /* =========================
           PETTY CASH LIMIT
        ========================= */

        if ($type === 'Petty Cash') {

            $total = collect(
                $validated['particulars']
            )->sum('Amount');

            if ($total > 1000) {

                return response()->json([
                    'message' =>
                        'Petty Cash requests cannot exceed ₱1,000.'
                ], 422);
            }
        }

        /* =========================
           REQUIRED ATTACHMENTS
        ========================= */

        if (
            $type === 'Request for Payment' &&
            !$request->hasFile('attachments')
        ) {

            return response()->json([
                'message' =>
                    'Attachment is required for Request for Payment.'
            ], 422);
        }

        DB::beginTransaction();

        try {

            /* =========================
               AUTH USER
            ========================= */
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'message' => 'Unauthorized'
                ], 401);
            }

            /* =========================
               GET EMPLOYEE RECORD
            ========================= */
            $employee = Employee::where('EmployeeNo', $user->employee_no)->first();

            if (!$employee) {
                return response()->json([
                    'message' => 'Employee record not found'
                ], 404);
            }

            /* =========================
               CREATE REQUISITION
            ========================= */
            $req = Requisition::create([
                'RequestId' => 'REQ-' . strtoupper(uniqid()),
                'Type' => $validated['Type'],
                'DateFiled' => now(),

                // ✅ CORRECT SOURCE (IMPORTANT FIX)
                'EmployeeNo' => $employee->EmployeeNo,
                'EmployeeName' => $employee->FirstName . ' ' . $employee->LastName,
                'Department' => $employee->Department,

                'StartDateNeeded' => $validated['StartDateNeeded'],
                'EndDateNeeded' => $validated['EndDateNeeded'],
                'Remarks' => $validated['Remarks'] ?? null,

                'Status' => Requisition::STATUS_PENDING,
            ]);

            $financeUsers = User::query()
                ->where('role', 'adminaccounting')
                ->get();

            foreach ($financeUsers as $finance) {

                $notification = Notification::create([
                    'user_id' => $finance->id,

                    'type' => 'requisition',

                    'title' => 'New Requisition Request',

                    'message' =>
                        "{$employee->FirstName} {$employee->LastName} submitted a requisition request.",

                    'related_type' => 'requisition',

                    'related_id' => $req->id,

                    'action_url' => '/dashboard/adminaccounting/finance-requisition',
                ]);

                event(
                    new NotificationCreated(
                        $notification
                    )
                );
            }

            /* =========================
               PARTICULARS
            ========================= */
            $total = 0;

            foreach ($validated['particulars'] as $item) {

                RequisitionParticular::create([
                    'RequestId' => $req->RequestId,
                    'ParticularId' => 'PAR-' . uniqid(),
                    'Particulars' => $item['Particulars'],
                    'Amount' => $item['Amount'],
                ]);

                $total += $item['Amount'];
            }

            $req->update([
                'TotalAmount' => $total
            ]);

            /* =========================
               ATTACHMENTS
            ========================= */
            if ($request->hasFile('attachments')) {

                foreach ($request->file('attachments') as $file) {

                    $path = $file->store('requisition_attachments', 'public');

                    RequisitionAttachment::create([
                        'RequestId' => $req->RequestId, // 🔥 FIX (consistent)
                        'FileName' => $file->getClientOriginalName(),
                        'FilePath' => $path,
                        'FileType' => $file->getClientMimeType(),
                        'FileSize' => $file->getSize(),
                    ]);
                }
            }

            /* =========================
               LOG
            ========================= */
            $this->log($req, 'Submitted', $user);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Request created successfully',
                'data' => $req->load(['particulars', 'attachments'])
            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'message' => 'Failed to create request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /* =========================
       UPDATE STATUS
    ========================= */
    public function updateStatus(Request $request, $id)
    {
        // ✅ VALIDATION
        $validated = $request->validate([
            'status' => 'required|in:Checked,Approved,Rejected',
            'reason' => 'nullable|string|max:255'
        ]);

        // ✅ AUTH CHECK (FIXES YOUR ERROR)
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        // ✅ ROLE CHECK
        if (!in_array($user->role, ['adminaccounting', 'adminhr'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized role'
            ], 403);
        }

        return DB::transaction(function () use ($validated, $id, $user) {

            $req = Requisition::lockForUpdate()->findOrFail($id);

            /* =========================
               ACCOUNTING FLOW
            ========================= */
            if ($user->role === 'adminaccounting') {

                if ($req->Status !== Requisition::STATUS_PENDING) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Already processed'
                    ], 400);
                }

                if ($validated['status'] === Requisition::STATUS_CHECKED) {

                    $req->update([
                        'Status' => Requisition::STATUS_CHECKED,
                        'CheckedBy' => $user->name,
                        'CheckedDate' => now(),
                    ]);

                    $this->log($req, 'Checked', $user);

                    /* =========================
                       NOTIFY EMPLOYEE
                    ========================= */
                    $employeeUser = User::query()
                        ->where('employee_no', $req->EmployeeNo)
                        ->first();

                    if ($employeeUser) {

                        $notification = Notification::create([
                            'user_id' => $employeeUser->id,

                            'type' => 'requisition',

                            'title' => 'Requisition Checked',

                            'message' =>
                                'Accounting has checked your requisition request.',

                            'related_type' => 'requisition',

                            'related_id' => $req->id,

                            'action_url' => '/dashboard/employee/requisition',
                        ]);

                        event(
                            new NotificationCreated(
                                $notification
                            )
                        );
                    }

                    /* =========================
                       NOTIFY HR
                    ========================= */
                    $hrUsers = User::query()
                        ->where('role', 'adminhr')
                        ->get();

                    foreach ($hrUsers as $hr) {

                        $notification = Notification::create([
                            'user_id' => $hr->id,

                            'type' => 'requisition',

                            'title' => 'Requisition Ready For Approval',

                            'message' =>
                                "Accounting has checked requisition {$req->RequestId}.",

                            'related_type' => 'requisition',

                            'related_id' => $req->id,

                            'action_url' => '/dashboard/adminhr/requisition-list',
                        ]);

                        event(
                            new NotificationCreated(
                                $notification
                            )
                        );
                    }

                } elseif ($validated['status'] === Requisition::STATUS_REJECTED) {

                    $req->update([
                        'Status' => Requisition::STATUS_REJECTED,
                        'DisapprovalReason' => $validated['reason'] ?? null,
                        'CheckedBy' => $user->name,
                        'CheckedDate' => now(),
                    ]);

                    $this->log($req, 'Rejected', $user, $validated['reason'] ?? null);

                    $employeeUser = User::query()
                        ->where('employee_no', $req->EmployeeNo)
                        ->first();

                    if ($employeeUser) {

                        $notification = Notification::create([
                            'user_id' => $employeeUser->id,

                            'type' => 'requisition',

                            'title' => 'Requisition Rejected',

                            'message' =>
                                'Accounting rejected your requisition request.',

                            'related_type' => 'requisition',

                            'related_id' => $req->id,

                            'action_url' => '/employee/requisition-list',
                        ]);

                        event(
                            new NotificationCreated(
                                $notification
                            )
                        );
                    }
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Processed by Accounting'
                ]);
            }

            /* =========================
               HR FLOW
            ========================= */
            if ($user->role === 'adminhr') {

                if ($req->Status !== Requisition::STATUS_CHECKED) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Not ready for approval'
                    ], 400);
                }

                if ($validated['status'] === Requisition::STATUS_APPROVED) {

                    $req->update([
                        'Status' => Requisition::STATUS_APPROVED,
                        'ApprovedBy' => $user->name,
                        'ApprovedDate' => now(),
                    ]);

                    $this->log($req, 'Approved', $user);

                    /* =========================
                       NOTIFY EMPLOYEE
                    ========================= */
                    $employeeUser = User::query()
                        ->where('employee_no', $req->EmployeeNo)
                        ->first();

                    if ($employeeUser) {

                        $notification = Notification::create([
                            'user_id' => $employeeUser->id,

                            'type' => 'requisition',

                            'title' => 'Requisition Approved',

                            'message' =>
                                'Your requisition request has been approved.',

                            'related_type' => 'requisition',

                            'related_id' => $req->id,

                            'action_url' => '/employee/requisition-list',
                        ]);

                        event(
                            new NotificationCreated(
                                $notification
                            )
                        );
                    }

                    /* =========================
                       NOTIFY ACCOUNTING
                    ========================= */
                    $financeUsers = User::query()
                        ->where('role', 'adminaccounting')
                        ->get();

                    foreach ($financeUsers as $finance) {

                        $notification = Notification::create([
                            'user_id' => $finance->id,

                            'type' => 'requisition',

                            'title' => 'Requisition Approved',

                            'message' =>
                                "HR approved requisition {$req->RequestId}.",

                            'related_type' => 'requisition',

                            'related_id' => $req->id,

                            'action_url' => '/finance/requisition-list',
                        ]);

                        event(
                            new NotificationCreated(
                                $notification
                            )
                        );
                    }

                } elseif ($validated['status'] === Requisition::STATUS_REJECTED) {

                    $req->update([
                        'Status' => Requisition::STATUS_REJECTED,
                        'DisapprovalReason' => $validated['reason'] ?? null,
                        'ApprovedBy' => $user->name,
                        'ApprovedDate' => now(),
                    ]);

                    $this->log($req, 'Rejected', $user, $validated['reason'] ?? null);

                    $employeeUser = User::query()
                        ->where('employee_no', $req->EmployeeNo)
                        ->first();

                    if ($employeeUser) {

                        $notification = Notification::create([
                            'user_id' => $employeeUser->id,

                            'type' => 'requisition',

                            'title' => 'Requisition Rejected',

                            'message' =>
                                'HR rejected your requisition request.',

                            'related_type' => 'requisition',

                            'related_id' => $req->id,

                            'action_url' => '/employee/requisition-list',
                        ]);

                        event(
                            new NotificationCreated(
                                $notification
                            )
                        );
                    }
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Processed by HR'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Invalid process'
            ], 400);
        });
    }

    /* =========================
       AVAILABLE FOR LIQUIDATION
    ========================= */
    public function availableLiquidation()
    {
        $user = auth()->user();

        $query = Requisition::query();

        // Approved only
        $query->where('Status', Requisition::STATUS_APPROVED);

        // Not yet liquidated
        $query->where(function ($q) {
            $q->whereNull('has_liquidation')
                ->orWhere('has_liquidation', 0);
        });

        // Optional: current employee only
        if ($user) {
            $query->where('EmployeeNo', $user->employee_no);
        }

        $data = $query
            ->orderBy('DateFiled', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }


    /* =========================
       UPLOAD ATTACHMENTS
    ========================= */
    public function uploadAttachments(Request $request, $id)
    {
        $request->validate([
            'attachments' => 'required|array',
            'attachments.*' => 'file|mimes:pdf,jpg,jpeg,png|max:5120'
        ]);

        $req = Requisition::findOrFail($id);

        $uploadedFiles = [];

        if ($request->hasFile('attachments')) {

            foreach ($request->file('attachments') as $file) {

                $path = $file->store('requisition_attachments', 'public');

                $uploadedFiles[] = RequisitionAttachment::create([
                    'RequestId' => $req->RequestId,
                    'FileName' => $file->getClientOriginalName(),
                    'FilePath' => $path,
                    'FileType' => $file->getClientMimeType(),
                    'FileSize' => $file->getSize(),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Files uploaded successfully',
            'data' => $uploadedFiles
        ]);
    }

    /* =========================
       DELETE ATTACHMENT
    ========================= */
    public function deleteAttachment($id)
    {
        $file = RequisitionAttachment::findOrFail($id);

        if (Storage::disk('public')->exists($file->FilePath)) {
            Storage::disk('public')->delete($file->FilePath);
        }

        $file->delete();

        return response()->json([
            'success' => true,
            'message' => 'Attachment deleted successfully'
        ]);
    }

    /* =========================
       LOG HELPER
    ========================= */
    private function log($req, $action, $user, $remarks = null)
    {
        RequisitionLog::create([
            'RequestId' => $req->RequestId,
            'Action' => $action,
            'PerformedBy' => $user->name ?? 'System',
            'PerformedAt' => now(),
            'Remarks' => $remarks
        ]);
    }
}