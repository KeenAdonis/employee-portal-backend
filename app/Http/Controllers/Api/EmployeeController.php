<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmployeeController extends Controller
{
    /* =========================
       🔐 HELPER: N8N WEBHOOK
    ========================= */
    private function sendToN8n($payload)
    {
        try {
            $response = Http::withHeaders([
                'x-api-key' => config('services.n8n.key'),
                'Content-Type' => 'application/json',
            ])
                ->timeout(5)
                ->retry(2, 100)
                ->post(config('services.n8n.url'), $payload);

            Log::info('N8N RESPONSE', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

        } catch (\Exception $e) {
            Log::error('n8n webhook failed: ' . $e->getMessage());
        }
    }

    /* =========================
       GET /api/employees
    ========================= */
    public function index(Request $request)
    {
        try {
            $query = Employee::query();

            if ($request->search) {
                $query->where(function ($q) use ($request) {
                    $q->where('FirstName', 'like', "%{$request->search}%")
                        ->orWhere('LastName', 'like', "%{$request->search}%")
                        ->orWhere('EmployeeNo', 'like', "%{$request->search}%");
                });
            }

            if ($request->department) {
                $query->where('Department', $request->department);
            }

            if ($request->status) {
                $query->where('Status', $request->status);
            }

            $employees = $query
                ->orderBy('DateHired', 'desc')
                ->paginate($request->per_page ?? 10);

            return response()->json([
                'success' => true,
                'data' => $employees
            ]);

        } catch (\Exception $e) {
            Log::error('Fetch Employees Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch employees'
            ], 500);
        }
    }

    /* =========================
       CREATE EMPLOYEE
    ========================= */
    public function store(Request $request)
    {
        DB::beginTransaction();

        try {
            $request->validate([
                'EmployeeNo' => 'required|unique:tb_employee_list,EmployeeNo',
                'EmailAddress' => 'required|email|unique:users,email',
                'FirstName' => 'required|string|max:100',
                'LastName' => 'required|string|max:100',
            ]);

            $employee = Employee::create([
                'Status' => 'ACTIVE',
                'EmployeeNo' => $request->EmployeeNo,
                'FirstName' => $request->FirstName,
                'MiddleInitial' => $request->MiddleInitial,
                'LastName' => $request->LastName,
                'HomeAddress' => $request->HomeAddress,
                'Birthday' => $request->Birthday,
                'Gender' => $request->Gender,
                'CivilStatus' => $request->CivilStatus,
                'ContactNumber' => $request->ContactNumber,
                'EmailAddress' => $request->EmailAddress,
                'DateHired' => $request->DateHired,
                'Department' => $request->Department,
                'CompanyStatus' => $request->CompanyStatus,
                'Position' => $request->Position,
                'JobLevel' => $request->JobLevel,
                'MonthlySalary' => $request->MonthlySalary,
                'SSSNumber' => $request->SSSNumber,
                'PhilHealthNumber' => $request->PhilHealthNumber,
                'PagIbigNumber' => $request->PagIbigNumber,
                'TIN' => $request->TIN,
            ]);

            $plainPassword = Str::random(10);

            $user = User::create([
                'name' => $request->FirstName . ' ' . $request->LastName,
                'email' => $request->EmailAddress,
                'password' => Hash::make($plainPassword),
                'role' => 'employee',
                'employee_no' => $request->EmployeeNo,
                'status' => 'ACTIVE', // ✅ unified
                'is_admin' => 0,
                'is_temp_password' => true,
            ]);

            /* 🔐 SECURED N8N */
            $this->sendToN8n([
                'email' => $user->email,
                'employee_no' => $user->employee_no,
                'temp_password' => $plainPassword,
            ]);

            DB::commit();

            Log::info('Employee created', [
                'employee_no' => $employee->EmployeeNo
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Employee created successfully',
                'data' => $employee
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {

            DB::rollBack();

            Log::error('Employee Create Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create employee'
            ], 500);
        }
    }

    /* =========================
       UPDATE EMPLOYEE
    ========================= */
    public function update(Request $request, $employeeNo)
    {
        DB::beginTransaction();

        try {
            $request->validate([
                'EmailAddress' => 'required|email|unique:users,email,' . $employeeNo . ',employee_no',
            ]);

            $employee = Employee::where('EmployeeNo', $employeeNo)->firstOrFail();

            $employee->update([
                'FirstName' => $request->FirstName,
                'MiddleInitial' => $request->MiddleInitial,
                'LastName' => $request->LastName,
                'EmailAddress' => $request->EmailAddress,
                'ContactNumber' => $request->ContactNumber,
                'Department' => $request->Department,
                'Position' => $request->Position,
            ]);

            User::where('employee_no', $employeeNo)->update([
                'name' => $request->FirstName . ' ' . $request->LastName,
                'email' => $request->EmailAddress,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Employee updated successfully'
            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            Log::error('Employee Update Error: ' . $e->getMessage());

            return response()->json([
                'message' => 'Update failed'
            ], 500);
        }
    }

    /* =========================
       EXPORT CSV (SAFE)
    ========================= */
    public function export(Request $request)
    {
        $headers = [
            "Content-Type" => "text/csv",
            "Content-Disposition" => "attachment; filename=employees.csv",
        ];

        $callback = function () use ($request) {
            $file = fopen('php://output', 'w');

            fputcsv($file, [
                'Employee No',
                'First Name',
                'Last Name',
                'Email',
                'Department',
                'Status',
                'Date Hired'
            ]);

            Employee::query()
                ->when($request->search, function ($q) use ($request) {
                    $q->where('FirstName', 'like', "%{$request->search}%")
                        ->orWhere('LastName', 'like', "%{$request->search}%");
                })
                ->chunk(500, function ($employees) use ($file) {
                    foreach ($employees as $emp) {
                        fputcsv($file, [
                            $emp->EmployeeNo,
                            $emp->FirstName,
                            $emp->LastName,
                            $emp->EmailAddress,
                            $emp->Department,
                            $emp->Status,
                            $emp->DateHired,
                        ]);
                    }
                });

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /* =========================
       TOGGLE STATUS
    ========================= */
    public function toggleStatus($employeeNo)
    {
        $employee = Employee::where('EmployeeNo', $employeeNo)->firstOrFail();

        $employee->Status = $employee->Status === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE';
        $employee->save();

        User::where('employee_no', $employeeNo)->update([
            'status' => $employee->Status
        ]);

        return response()->json([
            'success' => true,
            'status' => $employee->Status
        ]);
    }

    /* =========================
       RESET PASSWORD
    ========================= */
    public function sendPassword($employeeNo)
    {
        $user = User::where('employee_no', $employeeNo)->firstOrFail();

        if ($user->status !== 'ACTIVE') {
            return response()->json([
                'message' => 'Cannot reset password for inactive user'
            ], 403);
        }

        $plainPassword = Str::random(10);

        $user->password = Hash::make($plainPassword);
        $user->is_temp_password = true;
        $user->tokens()->delete();
        $user->save();

        $this->sendToN8n([
            'email' => $user->email,
            'employee_no' => $user->employee_no,
            'temp_password' => $plainPassword,
        ]);

        Log::info('Password reset', [
            'employee_no' => $employeeNo
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Temporary password sent successfully'
        ]);
    }
}