<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Overtime;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OvertimeController extends Controller
{
    /* =========================
       GET /api/overtime
    ========================= */
    public function index(Request $request)
    {
        try {
            $query = Overtime::with([
                'employee:EmployeeNo,Position',
                'accomplishments:RequestId,Task,Category,TaskStatus'
            ]);

            /* SEARCH */
            if ($request->search) {
                $query->where(function ($q) use ($request) {
                    $q->where('EmployeeName', 'like', "%{$request->search}%")
                        ->orWhere('EmployeeNo', 'like', "%{$request->search}%");
                });
            }

            /* FILTER */
            if ($request->filled('status')) {
                $statuses = explode(',', $request->status);
                $query->whereIn('Status', $statuses);
            }

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
            $overtime = Overtime::create([
                'RequestId' => uniqid('OT-'),
                'DateFiled' => now(),
                'EmployeeNo' => $request->EmployeeNo,
                'EmployeeName' => $request->EmployeeName,
                'OvertimeDate' => $request->OvertimeDate,
                'TimeFrom' => $request->TimeFrom,
                'TimeTo' => $request->TimeTo,
                'TotalHours' => $request->TotalHours,
                'OvertimeReason' => $request->OvertimeReason,
                'Status' => Overtime::STATUS_PENDING,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Overtime request submitted',
                'data' => $overtime
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit overtime',
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

        return response()->json([
            'success' => true,
            'message' => 'Overtime rejected'
        ]);
    }
}