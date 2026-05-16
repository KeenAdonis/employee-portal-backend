<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SecureDocumentLog;
use Illuminate\Http\Request;

class LogController extends Controller
{
    public function index(Request $request)
    {
        $query = SecureDocumentLog::query();

        // 🔍 SEARCH
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('email', 'like', "%{$request->search}%")
                  ->orWhere('employee_name', 'like', "%{$request->search}%")
                  ->orWhere('message', 'like', "%{$request->search}%");
            });
        }

        // 🎯 FILTER ACTION
        if ($request->action) {
            $query->where('action', $request->action);
        }

        // 🎯 FILTER STATUS
        if ($request->status) {
            $query->where('status', $request->status);
        }

        return response()->json([
            'success' => true,
            'data' => $query
                ->orderByDesc('id') // ✅ FIXED
                ->paginate(10),
        ]);
    }
}