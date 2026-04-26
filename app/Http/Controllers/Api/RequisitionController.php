<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\Requisition;
use App\Models\RequisitionLog;
use App\Models\RequisitionAttachment;

class RequisitionController extends Controller
{
    /* =========================
       LIST (INDEX)
    ========================= */
    public function index(Request $request)
    {
        $query = Requisition::with([
            'particulars',
            'attachments',
            'logs'
        ]);

        // STATUS FILTER
        if ($request->status) {
            $statuses = explode(',', $request->status);
            $query->whereIn('Status', $statuses);
        }

        // SEARCH
        if ($request->search) {
            $query->where('EmployeeName', 'like', "%{$request->search}%");
        }

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
            'EmployeeNo' => 'required|string|max:50',
            'EmployeeName' => 'required|string|max:100',
            'Department' => 'required|string|max:100',

            'StartDateNeeded' => 'required|date',
            'EndDateNeeded' => 'required|date',

            'Remarks' => 'nullable|string|max:255',

            // particulars
            'particulars' => 'required|array|min:1',
            'particulars.*.Particulars' => 'required|string|max:150',
            'particulars.*.Amount' => 'required|numeric|min:0',
        ]);

        return DB::transaction(function () use ($validated) {

            /* =========================
               CREATE MAIN
            ========================= */
            $req = Requisition::create([
                'RequestId' => uniqid('REQ-'),
                'Type' => $validated['Type'],
                'DateFiled' => now(),

                'EmployeeNo' => $validated['EmployeeNo'],
                'EmployeeName' => $validated['EmployeeName'],
                'Department' => $validated['Department'],

                'StartDateNeeded' => $validated['StartDateNeeded'],
                'EndDateNeeded' => $validated['EndDateNeeded'],

                'Remarks' => $validated['Remarks'] ?? null,

                'Status' => Requisition::STATUS_PENDING,
            ]);

            /* =========================
               SAVE PARTICULARS
            ========================= */
            $total = 0;

            foreach ($validated['particulars'] as $item) {

                $req->particulars()->create([
                    'ParticularId' => uniqid('PRT-'),
                    'Particulars' => $item['Particulars'],
                    'Amount' => $item['Amount'],
                ]);

                $total += $item['Amount'];
            }

            /* =========================
               UPDATE TOTAL
            ========================= */
            $req->update([
                'TotalAmount' => $total
            ]);

            /* =========================
               LOG
            ========================= */
            $this->log($req, 'Submitted', auth()->user());

            return response()->json([
                'success' => true,
                'message' => 'Requisition submitted successfully',
                'data' => $req->load('particulars')
            ]);
        });
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
        if (!in_array($user->role, ['accountingadmin', 'adminhr'])) {
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
            if ($user->role === 'accountingadmin') {

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

                } elseif ($validated['status'] === Requisition::STATUS_REJECTED) {

                    $req->update([
                        'Status' => Requisition::STATUS_REJECTED,
                        'DisapprovalReason' => $validated['reason'] ?? null,
                        'CheckedBy' => $user->name,
                        'CheckedDate' => now(),
                    ]);

                    $this->log($req, 'Rejected', $user, $validated['reason'] ?? null);
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

                } elseif ($validated['status'] === Requisition::STATUS_REJECTED) {

                    $req->update([
                        'Status' => Requisition::STATUS_REJECTED,
                        'DisapprovalReason' => $validated['reason'] ?? null,
                        'ApprovedBy' => $user->name,
                        'ApprovedDate' => now(),
                    ]);

                    $this->log($req, 'Rejected', $user, $validated['reason'] ?? null);
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