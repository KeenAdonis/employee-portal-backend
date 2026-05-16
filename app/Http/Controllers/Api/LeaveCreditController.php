<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeaveCredit;

class LeaveCreditController extends Controller
{
    public function myCredits()
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        $credit = LeaveCredit::where(
            'EmployeeNo',
            $user->employee_no
        )->first();

        if (!$credit) {

            return response()->json([
                'success' => true,
                'data' => [
                    'VLBalance' => 0,
                    'SLBalance' => 0,
                    'ELBalance' => 0,
                    'MLBalance' => 0,
                    'PLBalance' => 0,
                    'BLBalance' => 0,
                    'BDLBalance' => 0,
                ]
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $credit
        ]);
    }
}