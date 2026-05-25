<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\DailyTimeRecord;
use App\Models\Notification;
use App\Models\User;
use App\Models\Employee;

use App\Events\NotificationCreated;

use Carbon\Carbon;

use Symfony\Component\HttpFoundation\StreamedResponse;

class DailyTimeRecordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | GET /api/dtr
    |--------------------------------------------------------------------------
    */

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

            $query = DailyTimeRecord::with([
                'employee:employee_id,EmployeeNo,FirstName,LastName,ProfileImage',
                'approver:id,name'
            ]);

            /*
|--------------------------------------------------------------------------
| ROLE FILTER
|--------------------------------------------------------------------------
*/

            if ($user->role === 'employee') {

                $employee = Employee::where(
                    'EmployeeNo',
                    $user->employee_no
                )->first();

                if (!$employee) {

                    return response()->json([
                        'success' => false,
                        'message' => 'Employee not found.'
                    ], 404);
                }

                $query->where(
                    'employee_id',
                    $employee->employee_id
                );
            }

            /*
            |--------------------------------------------------------------------------
            | SEARCH
            |--------------------------------------------------------------------------
            */

            if ($request->filled('search')) {

                $search = $request->search;

                $query->whereHas('employee', function ($q) use ($search) {

                    $q->where('EmployeeNo', 'like', "%{$search}%")
                        ->orWhere('FirstName', 'like', "%{$search}%")
                        ->orWhere('LastName', 'like', "%{$search}%");
                });
            }

            /*
            |--------------------------------------------------------------------------
            | STATUS FILTER
            |--------------------------------------------------------------------------
            */

            if (
                $request->filled('status') &&
                $request->status !== 'all'
            ) {

                $statuses = explode(',', $request->status);

                $query->whereIn('status', $statuses);
            }

            /*
            |--------------------------------------------------------------------------
            | DATE FILTER
            |--------------------------------------------------------------------------
            */

            if (
                $request->filled('from') &&
                $request->filled('to')
            ) {

                $query->whereBetween('date', [
                    $request->from,
                    $request->to
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | PAGINATION
            |--------------------------------------------------------------------------
            */

            $dtr = $query
                ->orderBy('date', 'desc')
                ->paginate(
                    $request->per_page ?? 10
                );

            return response()->json([
                'success' => true,
                'data' => $dtr
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch DTR',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | STORE DTR
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        try {

            \Log::info('DTR STORE START');

            $user = auth()->user();

            if (!$user) {

                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            \Log::info('AUTH USER', [
                'user_id' => $user->id,
                'employee_no' => $user->employee_no,
            ]);

            /*
            |--------------------------------------------------------------------------
            | VALIDATION
            |--------------------------------------------------------------------------
            */

            $request->validate([

                'date' => 'required|date',

                'time_in' => 'required',

                'time_out' => 'required',

                'break_out' => 'nullable',

                'break_in' => 'nullable',

                'remarks' => 'nullable|string|max:1000',
            ]);

            \Log::info('VALIDATION PASSED');

            /*
            |--------------------------------------------------------------------------
            | EMPLOYEE
            |--------------------------------------------------------------------------
            */

            $employee = Employee::where(
                'EmployeeNo',
                $user->employee_no
            )->first();

            if (!$employee) {

                return response()->json([
                    'success' => false,
                    'message' => 'Employee not found.'
                ], 404);
            }

            \Log::info('EMPLOYEE FOUND', [
                'employee_id' => $employee->employee_id
            ]);

            /*
            |--------------------------------------------------------------------------
            | CHECK DUPLICATE
            |--------------------------------------------------------------------------
            */

            $existing = DailyTimeRecord::where(
                'employee_id',
                $employee->employee_id
            )
                ->where('date', $request->date)
                ->first();

            if ($existing) {

                return response()->json([
                    'success' => false,
                    'message' => 'DTR already exists for this date.'
                ], 422);
            }

            \Log::info('NO DUPLICATE FOUND');

            /*
            |--------------------------------------------------------------------------
            | CREATE DTR
            |--------------------------------------------------------------------------
            */

            $dtr = new DailyTimeRecord();

            $dtr->employee_id =
                $employee->employee_id;

            $dtr->date = $request->date;

            $dtr->remarks = $request->remarks;

            $dtr->status = 'Pending';

            $dtr->source_type = 'manual';

            /*
            |--------------------------------------------------------------------------
            | TIME PARSING
            |--------------------------------------------------------------------------
            */

            \Log::info('BEFORE TIME PARSE');

            $dtr->time_in =
                Carbon::parse(
                    $request->time_in
                )->format('H:i:s');

            $dtr->break_out =
                $request->break_out
                ? Carbon::parse(
                    $request->break_out
                )->format('H:i:s')
                : null;

            $dtr->break_in =
                $request->break_in
                ? Carbon::parse(
                    $request->break_in
                )->format('H:i:s')
                : null;

            $dtr->time_out =
                Carbon::parse(
                    $request->time_out
                )->format('H:i:s');

            \Log::info('TIME PARSE DONE', [

                'time_in' => $dtr->time_in,

                'break_out' => $dtr->break_out,

                'break_in' => $dtr->break_in,

                'time_out' => $dtr->time_out,
            ]);

            /*
            |--------------------------------------------------------------------------
            | COMPUTE HOURS
            |--------------------------------------------------------------------------
            */

            $dtr->calculateHours();

            \Log::info('CALCULATE HOURS DONE', [

                'work_hours' =>
                    $dtr->total_work_hours,

                'break_hours' =>
                    $dtr->total_break_hours,

                'ot_hours' =>
                    $dtr->overtime_hours,
            ]);

            /*
            |--------------------------------------------------------------------------
            | SAVE
            |--------------------------------------------------------------------------
            */

            $dtr->save();

            \Log::info('DTR SAVED', [
                'dtr_id' => $dtr->id
            ]);

            /*
            |--------------------------------------------------------------------------
            | NOTIFY ADMIN HR
            |--------------------------------------------------------------------------
            */

            $adminUsers = User::query()
                ->where('role', 'adminhr')
                ->get();

            \Log::info('ADMIN USERS FOUND', [
                'count' => $adminUsers->count()
            ]);

            foreach ($adminUsers as $admin) {

                \Log::info('CREATING NOTIFICATION', [
                    'admin_id' => $admin->id
                ]);

                $notification = Notification::create([

                    'user_id' => $admin->id,

                    'type' => 'dtr',

                    'title' => 'New DTR Submitted',

                    'message' =>
                        'DTR submitted successfully.',

                    'related_type' => 'dtr',

                    'related_id' => $dtr->id,

                    'action_url' =>
                        '/dashboard/adminhr/dtr',
                ]);

                \Log::info('NOTIFICATION CREATED', [
                    'notification_id' => $notification->id
                ]);

                /*
                |--------------------------------------------------------------------------
                | EVENT
                |--------------------------------------------------------------------------
                */

                event(
                    new NotificationCreated(
                        $notification
                    )
                );

                \Log::info('EVENT DISPATCHED');
            }

            /*
            |--------------------------------------------------------------------------
            | RESPONSE
            |--------------------------------------------------------------------------
            */

            return response()->json([
                'success' => true,
                'message' => 'DTR submitted successfully.',
                'data' => $dtr
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {

            \Log::error('DTR VALIDATION ERROR', [
                'errors' => $e->errors()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {

            \Log::error('DTR STORE ERROR', [

                'message' => $e->getMessage(),

                'line' => $e->getLine(),

                'file' => $e->getFile(),

                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit DTR',
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE DTR
    |--------------------------------------------------------------------------
    */

    public function update(Request $request, $id)
    {
        try {

            $user = auth()->user();

            $dtr = DailyTimeRecord::findOrFail($id);

            $dtr->load('employee');

            /*
            |--------------------------------------------------------------------------
            | EMPLOYEE OWNERSHIP
            |--------------------------------------------------------------------------
            */

            if (
                $user->role === 'employee' &&
                $dtr->employee_id !== $user->employee_id
            ) {

                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized action.'
                ], 403);
            }

            /*
            |--------------------------------------------------------------------------
            | LOCK APPROVED
            |--------------------------------------------------------------------------
            */

            if ($dtr->status === 'Approved') {

                return response()->json([
                    'success' => false,
                    'message' =>
                        'Approved DTR can no longer be edited.'
                ], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | VALIDATION
            |--------------------------------------------------------------------------
            */

            $request->validate([

                'time_in' => 'required',

                'time_out' => 'required',

                'break_out' => 'nullable',

                'break_in' => 'nullable',

                'remarks' => 'nullable|string|max:1000',
            ]);

            /*
            |--------------------------------------------------------------------------
            | UPDATE
            |--------------------------------------------------------------------------
            */

            $dtr->time_in = $request->time_in;

            $dtr->break_out = $request->break_out;

            $dtr->break_in = $request->break_in;

            $dtr->time_out = $request->time_out;

            $dtr->remarks = $request->remarks;

            /*
            |--------------------------------------------------------------------------
            | RESET STATUS
            |--------------------------------------------------------------------------
            */

            $dtr->status = 'Pending';

            $dtr->approved_by = null;

            $dtr->approved_at = null;

            /*
            |--------------------------------------------------------------------------
            | RECALCULATE
            |--------------------------------------------------------------------------
            */

            $dtr->calculateHours();

            $dtr->save();

            return response()->json([
                'success' => true,
                'message' => 'DTR updated successfully.',
                'data' => $dtr
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
                'message' => 'Failed to update DTR',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | APPROVE
    |--------------------------------------------------------------------------
    */

    public function approve($id)
    {
        try {

            $dtr = DailyTimeRecord::findOrFail($id);

            $dtr->update([

                'status' => 'Approved',

                'approved_by' => auth()->id(),

                'approved_at' => now(),
            ]);

            /*
            |--------------------------------------------------------------------------
            | NOTIFY EMPLOYEE
            |--------------------------------------------------------------------------
            */

            $employeeUser = User::query()
                ->where('employee_no', optional($dtr->employee)->EmployeeNo)
                ->first();

            if ($employeeUser) {

                $notification = Notification::create([

                    'user_id' => $employeeUser->id,

                    'type' => 'dtr',

                    'title' => 'DTR Approved',

                    'message' =>
                        'Your DTR has been approved.',

                    'related_type' => 'dtr',

                    'related_id' => $dtr->id,

                    'action_url' =>
                        '/dashboard/employee/dtr',
                ]);

                event(
                    new NotificationCreated(
                        $notification
                    )
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'DTR approved successfully.'
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Failed to approve DTR',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | REJECT
    |--------------------------------------------------------------------------
    */

    public function reject(Request $request, $id)
    {
        try {

            $request->validate([
                'remarks' => 'required|string|max:1000'
            ]);

            $dtr = DailyTimeRecord::findOrFail($id);

            $dtr->update([

                'status' => 'Rejected',

                'remarks' => $request->remarks,

                'approved_by' => auth()->id(),

                'approved_at' => now(),
            ]);

            /*
            |--------------------------------------------------------------------------
            | NOTIFY EMPLOYEE
            |--------------------------------------------------------------------------
            */

            $employeeUser = User::query()
                ->where('employee_no', optional($dtr->employee)->EmployeeNo)
                ->first();

            if ($employeeUser) {

                $notification = Notification::create([

                    'user_id' => $employeeUser->id,

                    'type' => 'dtr',

                    'title' => 'DTR Rejected',

                    'message' =>
                        'Your DTR has been rejected.',

                    'related_type' => 'dtr',

                    'related_id' => $dtr->id,

                    'action_url' =>
                        '/dashboard/employee/dtr',
                ]);

                event(
                    new NotificationCreated(
                        $notification
                    )
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'DTR rejected successfully.'
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
                'message' => 'Failed to reject DTR',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | EXPORT CSV
    |--------------------------------------------------------------------------
    */

    public function export(Request $request): StreamedResponse
    {
        $request->validate([

            'from' => 'required|date',

            'to' => 'required|date',
        ]);

        $query = DailyTimeRecord::with('employee')
            ->whereBetween('date', [
                $request->from,
                $request->to
            ]);

        if (
            $request->filled('status') &&
            $request->status !== 'all'
        ) {

            $statuses = explode(',', $request->status);

            $query->whereIn('status', $statuses);
        }

        $filename =
            "dtr_{$request->from}_to_{$request->to}.csv";

        return response()->stream(function () use ($query) {

            $file = fopen('php://output', 'w');

            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($file, [

                'Date',

                'Employee No',

                'Employee Name',

                'Time In',

                'Break Out',

                'Break In',

                'Time Out',

                'Work Hours',

                'OT Hours',

                'Late Minutes',

                'Undertime Minutes',

                'Status',
            ]);

            $data = $query
                ->orderBy('date', 'desc')
                ->get();

            foreach ($data as $row) {

                fputcsv($file, [

                    $row->date,

                    $row->employee?->EmployeeNo,

                    optional($row->employee)->FirstName .
                    ' ' .
                    optional($row->employee)->LastName,

                    $row->time_in,

                    $row->break_out,

                    $row->break_in,

                    $row->time_out,

                    $row->total_work_hours,

                    $row->overtime_hours,

                    $row->late_minutes,

                    $row->undertime_minutes,

                    strtoupper($row->status),
                ]);
            }

            fclose($file);

        }, 200, [

            'Content-Type' => 'text/csv',

            'Content-Disposition' =>
                "attachment; filename={$filename}",
        ]);
    }
}