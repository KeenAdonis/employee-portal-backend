<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Leave;
use App\Models\LeaveCredit;
use Illuminate\Support\Facades\DB;

class LeaveController extends Controller
{
    public function index(Request $request)
    {
        $query = Leave::with([
            'employee:EmployeeNo,Position',
            'credit'
        ]);

        if ($request->search) {
            $query->where('EmployeeName', 'like', "%{$request->search}%");
        }

        if ($request->status) {
            $statuses = explode(',', $request->status);
            $query->whereIn('Status', $statuses);
        }

        $leave = $query
            ->orderBy('DateFiled', 'desc')
            ->paginate($request->per_page ?? 10);

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

            $attachmentPath = null;

            // ================= FILE UPLOAD =================
            if ($request->hasFile('Attachment')) {

                $file = $request->file('Attachment');

                $attachmentPath = $file->store('leave', 'public');
                // example: leave/abc123.pdf
            }

            $leave = Leave::create([
                'RequestId' => uniqid('LV-'),
                'EmployeeNo' => $request->EmployeeNo,
                'EmployeeName' => $request->EmployeeName,
                'DateFiled' => now(),
                'DateFrom' => $request->DateFrom,
                'DateTo' => $request->DateTo,
                'TotalDays' => $request->TotalDays,
                'LeaveType' => $request->LeaveType,
                'LeaveDuration' => $request->LeaveDuration,
                'Reason' => $request->Reason,
                'Status' => Leave::STATUS_PENDING,

                // ✅ SAVE FILE PATH
                'Attachment' => $attachmentPath,
            ]);

            $request->validate([
                'Attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048'
            ]);

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