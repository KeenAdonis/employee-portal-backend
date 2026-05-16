<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

use App\Models\Liquidation;
use App\Models\LiquidationParticular;
use App\Models\LiquidationLog;
use App\Models\Requisition;
use App\Models\Notification;
use App\Models\User;

use App\Events\NotificationCreated;

class LiquidationController extends Controller
{
    /**
     * STORE (Submit Liquidation)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'request_id' => 'required|string',
            'cash_advance' => 'required|numeric|min:0',
            'particulars' => 'required|array|min:1',
            'particulars.*.particulars' => 'required|string',
            'particulars.*.amount' => 'required|numeric|min:0',
            'particulars.*.or_no' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation Error',
                'errors' => $validator->errors()
            ], 422);
        }

        $existingLiquidation = Liquidation::where(
            'request_id',
            $request->request_id
        )->latest()->first();

        if (
            $existingLiquidation &&
            $existingLiquidation->status !== 'Rejected'
        ) {
            return response()->json([
                'message' =>
                    'This request already has an active liquidation.'
            ], 400);
        }

        /* =========================
        UPDATE REQUISITION
        ========================= */

        try {
            DB::beginTransaction();

            // 🧾 Create liquidation
            $liq = Liquidation::create([
                'request_id' => $request->request_id,
                'cash_advance' => $request->cash_advance,
                'total_expenses' => 0,
                'amount_reimbursement' => 0,
                'amount_returned' => 0,
                'status' => 'Pending',
                'remarks' => $request->remarks ?? null,
            ]);

            // 🧾 Save particulars
            $total = 0;

            foreach ($request->particulars as $p) {
                LiquidationParticular::create([
                    'liquidation_id' => $liq->id,
                    'particulars' => $p['particulars'],
                    'or_no' => $p['or_no'] ?? null,
                    'amount' => $p['amount'],
                ]);

                $total += $p['amount'];
            }

            // 💰 COMPUTE
            $cash = $request->cash_advance;

            $reimbursement = 0;
            $returned = 0;

            if ($total > $cash) {
                $reimbursement = $total - $cash;
            } elseif ($total < $cash) {
                $returned = $cash - $total;
            }

            // 💾 Update totals
            $liq->update([
                'total_expenses' => $total,
                'amount_reimbursement' => $reimbursement,
                'amount_returned' => $returned,
            ]);

            // 📝 Log
            $this->log($liq->id, 'Submitted', auth()->user()->name ?? 'System');

            /* =========================
               NOTIFY ACCOUNTING
            ========================= */
            $financeUsers = User::query()
                ->where('role', 'adminaccounting')
                ->get();

            foreach ($financeUsers as $finance) {

                $notification = Notification::create([
                    'user_id' => $finance->id,

                    'type' => 'liquidation',

                    'title' => 'New Liquidation Request',

                    'message' =>
                        'A liquidation request has been submitted.',

                    'related_type' => 'liquidation',

                    'related_id' => $liq->id,

                    'action_url' => '/dashboard/adminaccounting/finance-liquidation',
                ]);

                event(
                    new NotificationCreated(
                        $notification
                    )
                );
            }

            DB::commit();

            return response()->json([
                'message' => 'Liquidation submitted successfully',
                'data' => $liq
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to submit liquidation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * INDEX (List)
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        $query = Liquidation::with([
            'particulars',
            'requisition.employee',
        ]);

        /* =========================
           🔐 ROLE FILTER
        ========================= */

        // 👤 EMPLOYEE → sariling liquidation lang
        if ($user->role === 'employee') {
            $query->whereHas('requisition', function ($q) use ($user) {
                $q->where('EmployeeNo', $user->employee_no);
            });
        }

        // 💰 ACCOUNTING → Pending + Checked
        elseif ($user->role === 'adminaccounting') {
            $query->whereIn('status', [
                'Pending',
                'Checked',
                'Rejected',
                'Approved',
            ]);
        }

        // 👑 HR → Checked only
        elseif ($user->role === 'adminhr') {
            $query->whereIn('status', [
                'Checked',
                'Approved',
                'Rejected',
            ]);
        }

        /* =========================
           📊 STATUS FILTER (OPTIONAL)
        ========================= */
        if ($request->filled('status')) {
            $statuses = explode(',', $request->status);
            $query->whereIn('status', $statuses);
        }

        /* =========================
           🔍 SEARCH
        ========================= */
        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('request_id', 'like', "%{$search}%")
                    ->orWhere('remarks', 'like', "%{$search}%");
            });
        }

        /* =========================
           📄 PAGINATION
        ========================= */
        $data = $query
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 10);

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * SHOW (with particulars)
     */
    public function show($id)
    {
        $liq = Liquidation::with('particulars')->findOrFail($id);

        return response()->json($liq);
    }

    /**
     * UPDATE STATUS (Accounting + HR)
     */
    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:Checked,Approved,Rejected',
            'remarks' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $liq = Liquidation::with('requisition')->findOrFail($id);
        $user = auth()->user();

        try {
            DB::beginTransaction();

            // 🔐 Role-based control
            if ($user->role === 'adminaccounting') {
                if ($liq->status !== 'Pending') {
                    return response()->json([
                        'message' => 'Only Pending can be processed by Accounting'
                    ], 400);
                }

                if (!in_array($request->status, ['Checked', 'Rejected'])) {
                    return response()->json([
                        'message' => 'Invalid status for Accounting'
                    ], 400);
                }
            }

            if ($user->role === 'adminhr') {
                if ($liq->status !== 'Checked') {
                    return response()->json([
                        'message' => 'Only Checked can be approved by HR'
                    ], 400);
                }

                if (!in_array($request->status, ['Approved', 'Rejected'])) {
                    return response()->json([
                        'message' => 'Invalid status for HR'
                    ], 400);
                }
            }

            // 💾 Update
            $liq->update([
                'status' => $request->status,
                'remarks' => $request->remarks ?? $liq->remarks,
            ]);

            // 📝 Log
            $this->log($liq->id, $request->status, $user->name);

            if ($request->status === 'Approved') {

                /* =========================
                   FINALIZE REQUISITION
                ========================= */

                Requisition::where(
                    'RequestId',
                    $liq->request_id
                )->update([

                            'has_liquidation' => 1,

                            'LiquidatedDate' => now(),

                            'Status' =>
                                Requisition::STATUS_LIQUIDATED,

                        ]);

                /* =========================
                   NOTIFY EMPLOYEE
                ========================= */
                $employeeUser = User::query()
                    ->where('employee_no', $liq->requisition->EmployeeNo)
                    ->first();

                if ($employeeUser) {

                    $notification = Notification::create([
                        'user_id' => $employeeUser->id,

                        'type' => 'liquidation',

                        'title' => 'Liquidation Approved',

                        'message' =>
                            'Your liquidation request has been approved.',

                        'related_type' => 'liquidation',

                        'related_id' => $liq->id,

                        'action_url' => '/dashboard/employee/liquidation',
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

                        'type' => 'liquidation',

                        'title' => 'Liquidation Approved',

                        'message' =>
                            'HR approved a liquidation request.',

                        'related_type' => 'liquidation',

                        'related_id' => $liq->id,

                        'action_url' => '/dashboard/adminaccounting/finance-liquidation',
                    ]);

                    event(
                        new NotificationCreated(
                            $notification
                        )
                    );
                }
            }

            if ($request->status === 'Checked') {

                $employeeUser = User::query()
                    ->where('employee_no', $liq->requisition->EmployeeNo)
                    ->first();

                /* =========================
                   NOTIFY EMPLOYEE
                ========================= */
                if ($employeeUser) {

                    $notification = Notification::create([
                        'user_id' => $employeeUser->id,

                        'type' => 'liquidation',

                        'title' => 'Liquidation Checked',

                        'message' =>
                            'Accounting checked your liquidation request.',

                        'related_type' => 'liquidation',

                        'related_id' => $liq->id,

                        'action_url' => '/dashboard/employee/liquidation',
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

                        'type' => 'liquidation',

                        'title' => 'Liquidation Ready For Approval',

                        'message' =>
                            'Accounting checked a liquidation request.',

                        'related_type' => 'liquidation',

                        'related_id' => $liq->id,

                        'action_url' => '/dashboard/adminhr/liquidation-list',
                    ]);

                    event(
                        new NotificationCreated(
                            $notification
                        )
                    );
                }
            }

            if (
                $request->status === 'Rejected' &&
                $user->role === 'adminaccounting'
            ) {

                $employeeUser = User::query()
                    ->where('employee_no', $liq->requisition->EmployeeNo)
                    ->first();

                if ($employeeUser) {

                    $notification = Notification::create([
                        'user_id' => $employeeUser->id,

                        'type' => 'liquidation',

                        'title' => 'Liquidation Rejected',

                        'message' =>
                            'Accounting rejected your liquidation request.',

                        'related_type' => 'liquidation',

                        'related_id' => $liq->id,

                        'action_url' => '/dashboard/employee/liquidation',
                    ]);

                    event(
                        new NotificationCreated(
                            $notification
                        )
                    );
                }
            }


            if (
                $request->status === 'Rejected' &&
                $user->role === 'adminhr'
            ) {

                /* =========================
                   NOTIFY EMPLOYEE
                ========================= */
                $employeeUser = User::query()
                    ->where('employee_no', $liq->requisition->EmployeeNo)
                    ->first();

                if ($employeeUser) {

                    $notification = Notification::create([
                        'user_id' => $employeeUser->id,

                        'type' => 'liquidation',

                        'title' => 'Liquidation Rejected',

                        'message' =>
                            'HR rejected your liquidation request.',

                        'related_type' => 'liquidation',

                        'related_id' => $liq->id,

                        'action_url' => '/dashboard/employee/liquidation',
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

                        'type' => 'liquidation',

                        'title' => 'Liquidation Rejected',

                        'message' =>
                            'HR rejected a liquidation request.',

                        'related_type' => 'liquidation',

                        'related_id' => $liq->id,

                        'action_url' => '/dashboard/adminaccounting/finance-liquidation',
                    ]);

                    event(
                        new NotificationCreated(
                            $notification
                        )
                    );
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Status updated successfully',
                'data' => $liq
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to update status',
                'error' => $e->getMessage()
            ], 500);
        }
    }



    /**
     * LOG HELPER
     */
    private function log($liquidationId, $action, $user)
    {
        LiquidationLog::create([
            'liquidation_id' => $liquidationId,
            'action' => $action,
            'performed_by' => $user,
            'remarks' => null
        ]);
    }
}