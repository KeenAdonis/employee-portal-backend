<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\OvertimeController;
use App\Http\Controllers\Api\LeaveController;
use App\Http\Controllers\Api\RequisitionController;

/* =========================
   AUTH
========================= */
Route::post('/login', [AuthController::class, 'login']);

/* =========================
   PUBLIC (READ ONLY)
========================= */
Route::get('/requisition', [RequisitionController::class, 'index']);
Route::get('/employees', [EmployeeController::class, 'index']);
Route::get('/overtime', [OvertimeController::class, 'index']);
Route::get('/leave', [LeaveController::class, 'index']);

/* =========================
   PROTECTED (ACTIONS)
========================= */
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/change-password', [AuthController::class, 'changePassword']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    
    // WRITE ACTIONS ONLY
    Route::post('/employees', [EmployeeController::class, 'store']);
    Route::put('/employees/{employeeNo}', [EmployeeController::class, 'update']);
    Route::put('/employees/{employeeNo}/toggle-status', [EmployeeController::class, 'toggleStatus']);
    Route::post('/employees/{employeeNo}/send-password', [EmployeeController::class, 'sendPassword']);

    Route::post('/overtime', [OvertimeController::class, 'store']);
    Route::post('/overtime/{id}/pre-approve', [OvertimeController::class, 'preApprove']);
    Route::post('/overtime/{id}/approve', [OvertimeController::class, 'approve']);
    Route::post('/overtime/{id}/reject', [OvertimeController::class, 'reject']);

    Route::post('/leave', [LeaveController::class, 'store']);
    Route::post('/leave/{id}/approve', [LeaveController::class, 'approve']);
    Route::post('/leave/{id}/reject', [LeaveController::class, 'reject']);

    Route::post('/requisition', [RequisitionController::class, 'store']);
    Route::post('/requisition/{id}/status', [RequisitionController::class, 'updateStatus']);

    Route::post('/requisition/{id}/attachments', [RequisitionController::class, 'uploadAttachments']);
    Route::delete('/requisition/attachments/{id}', [RequisitionController::class, 'deleteAttachment']);
});

