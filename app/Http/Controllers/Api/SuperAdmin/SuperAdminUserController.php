<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Models\User;

class SuperAdminUserController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | ALLOWED ROLES
    |--------------------------------------------------------------------------
    */
    private array $allowedRoles = [
        'adminsuper',
        'adminhr',
        'adminaccounting',
        'admintesting',
        'adminmarketing',
        'admininventory',
        'employee',
    ];

    /*
    |--------------------------------------------------------------------------
    | GET USERS
    |--------------------------------------------------------------------------
    */
    public function index(Request $request)
    {
        try {

            $query = User::query();

            /*
            |--------------------------------------------------------------------------
            | SEARCH
            |--------------------------------------------------------------------------
            */
            if ($request->search) {

                $search = $request->search;

                $query->where(function ($q) use ($search) {

                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('employee_no', 'like', "%{$search}%");

                });
            }

            /*
            |--------------------------------------------------------------------------
            | ROLE FILTER
            |--------------------------------------------------------------------------
            */
            if ($request->role) {

                $query->where('role', $request->role);

            }

            /*
            |--------------------------------------------------------------------------
            | STATUS FILTER
            |--------------------------------------------------------------------------
            */
            if ($request->status) {

                $query->where('status', $request->status);

            }

            $users = $query
                ->latest()
                ->paginate($request->per_page ?? 10);

            return response()->json([
                'success' => true,
                'data' => $users,
            ]);

        } catch (\Exception $e) {

            Log::error('Fetch Users Error', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch users',
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | STORE USER
    |--------------------------------------------------------------------------
    */
    public function store(Request $request)
    {
        DB::beginTransaction();

        try {

            $request->validate([

                'name' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'email' => [
                    'required',
                    'email',
                    'unique:users,email',
                ],

                'password' => [
                    'required',
                    'string',
                    'min:8',
                ],

                'employee_no' => [
                    'required',
                    'string',
                    'max:255',
                    'unique:users,employee_no',
                ],

                'role' => [
                    'required',
                    Rule::in($this->allowedRoles),
                ],

            ]);

            $user = User::create([

                'name' => $request->name,

                'email' => $request->email,

                'password' => Hash::make(
                    $request->password
                ),

                'role' => $request->role,

                'status' => 'ACTIVE',

                'employee_no' => $request->employee_no,

                /*
                |--------------------------------------------------------------------------
                | AUTO ADMIN DETECTION
                |--------------------------------------------------------------------------
                */
                'is_admin' =>
                    $request->role !== 'employee',

                'is_temp_password' => false,

            ]);

            DB::commit();

            Log::info('Account created', [
                'user_id' => $user->id,
                'email' => $user->email,
                'role' => $user->role,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Account created successfully',
                'data' => $user,
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {

            DB::rollBack();

            Log::error('Create User Error', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create account',
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE USER
    |--------------------------------------------------------------------------
    */
    public function update(Request $request, $id)
    {
        DB::beginTransaction();

        try {

            $user = User::findOrFail($id);

            $request->validate([

                'name' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'email' => [
                    'required',
                    'email',
                    'unique:users,email,' . $user->id,
                ],

                'role' => [
                    'required',
                    Rule::in($this->allowedRoles),
                ],

                'status' => [
                    'required',
                    'in:ACTIVE,INACTIVE,SUSPENDED',
                ],

            ]);

            $user->update([

                'name' => $request->name,

                'email' => $request->email,

                'role' => $request->role,

                'status' => $request->status,

                /*
                |--------------------------------------------------------------------------
                | AUTO ADMIN DETECTION
                |--------------------------------------------------------------------------
                */
                'is_admin' =>
                    $request->role !== 'employee',

            ]);

            DB::commit();

            Log::info('User updated', [
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User updated successfully',
                'data' => $user,
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {

            DB::rollBack();

            Log::error('Update User Error', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update user',
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE USER
    |--------------------------------------------------------------------------
    */
    public function destroy($id)
    {
        DB::beginTransaction();

        try {

            $user = User::findOrFail($id);

            /*
            |--------------------------------------------------------------------------
            | PREVENT SELF DELETE
            |--------------------------------------------------------------------------
            */
            if (auth()->id() === $user->id) {

                return response()->json([
                    'success' => false,
                    'message' => 'You cannot delete your own account',
                ], 403);
            }

            /*
            |--------------------------------------------------------------------------
            | PREVENT DELETING LAST ADMINSUPER
            |--------------------------------------------------------------------------
            */
            if ($user->role === 'adminsuper') {

                $count = User::where(
                    'role',
                    'adminsuper'
                )->count();

                if ($count <= 1) {

                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot delete the last adminsuper account',
                    ], 403);
                }
            }

            $user->delete();

            DB::commit();

            Log::info('User deleted', [
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully',
            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            Log::error('Delete User Error', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete user',
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | TOGGLE STATUS
    |--------------------------------------------------------------------------
    */
    public function toggleStatus($id)
    {
        try {

            $user = User::findOrFail($id);

            /*
            |--------------------------------------------------------------------------
            | PREVENT SELF DISABLE
            |--------------------------------------------------------------------------
            */
            if (auth()->id() === $user->id) {

                return response()->json([
                    'success' => false,
                    'message' => 'You cannot disable your own account',
                ], 403);
            }

            $user->status =
                $user->status === 'ACTIVE'
                ? 'INACTIVE'
                : 'ACTIVE';

            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Status updated successfully',
                'status' => $user->status,
            ]);

        } catch (\Exception $e) {

            Log::error('Toggle Status Error', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update status',
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | RESET PASSWORD
    |--------------------------------------------------------------------------
    */
    public function resetPassword($id)
    {
        try {

            $user = User::findOrFail($id);

            $newPassword = Str::random(10);

            $user->password = Hash::make(
                $newPassword
            );

            $user->is_temp_password = true;

            /*
            |--------------------------------------------------------------------------
            | LOGOUT ALL TOKENS
            |--------------------------------------------------------------------------
            */
            $user->tokens()->delete();

            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Password reset successfully',
                'temp_password' => $newPassword,
            ]);

        } catch (\Exception $e) {

            Log::error('Reset Password Error', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to reset password',
            ], 500);
        }
    }
}