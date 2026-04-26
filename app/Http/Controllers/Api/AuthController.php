<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    /* =========================
       🔐 LOGIN
    ========================= */
    public function login(Request $request)
    {
        $request->validate([
            'type' => 'required|in:employee,admin',
            'email' => 'required|email',
            'password' => 'required|string|min:6',
            'employee_no' => 'nullable|string',
        ]);

        $user = null;

        /* =========================
           👨‍💼 EMPLOYEE LOGIN
        ========================= */
        if ($request->type === 'employee') {

            if (!$request->employee_no) {
                return response()->json([
                    'message' => 'Employee number is required'
                ], 422);
            }

            $user = User::where('email', $request->email)
                ->where('employee_no', $request->employee_no)
                ->where('is_admin', 0)
                ->first();
        }

        /* =========================
           🛡️ ADMIN LOGIN
        ========================= */ else {
            $user = User::where('email', $request->email)
                ->where('is_admin', 1)
                ->first();
        }

        /* =========================
           🚫 INVALID USER (ANTI TIMING ATTACK)
        ========================= */
        if (!$user) {
            Hash::check($request->password, '$2y$10$usesomesillystringforexamplehash');
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        /* =========================
           🔐 PASSWORD CHECK
        ========================= */
        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        /* =========================
           🚫 STATUS CHECK (FIXED 🔥)
        ========================= */
        if (strtoupper(trim($user->status)) !== 'ACTIVE') {
            return response()->json([
                'message' => 'Your account is inactive. Please contact administrator.'
            ], 403);
        }

        /* =========================
           🔄 TOKEN MANAGEMENT
        ========================= */
        $user->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        /* =========================
           📦 RESPONSE
        ========================= */
        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'employee_no' => $user->employee_no,
                'is_admin' => $user->is_admin,
                'status' => $user->status,
            ],
            'token' => $token,
            'force_change_password' => (bool) $user->is_temp_password,
        ]);
    }

    /* =========================
       🔐 CHANGE PASSWORD
    ========================= */
    public function changePassword(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'current_password' => ['required'],
            'new_password' => [
                'required',
                'confirmed',
                Password::min(8)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],
        ]);

        /* =========================
           ❌ CURRENT PASSWORD CHECK
        ========================= */
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect'
            ], 422);
        }

        /* =========================
           🚫 PREVENT SAME PASSWORD
        ========================= */
        if (Hash::check($request->new_password, $user->password)) {
            return response()->json([
                'message' => 'New password must be different from current password'
            ], 422);
        }

        /* =========================
           💾 UPDATE PASSWORD
        ========================= */
        $user->password = Hash::make($request->new_password);
        $user->is_temp_password = false;

        // 🔥 Normalize status (extra safety)
        $user->status = strtoupper(trim($user->status));

        $user->save();

        /* =========================
           🔄 REVOKE OLD TOKENS
        ========================= */
        $user->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Password updated successfully',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ]
        ]);
    }

    /* =========================
       👤 CURRENT USER
    ========================= */
    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    /* =========================
       🚪 LOGOUT
    ========================= */
    public function logout(Request $request)
    {
        if ($request->user()) {
            $request->user()->currentAccessToken()->delete();
        }

        return response()->json([
            'message' => 'Logged out successfully'
        ]);
    }
}