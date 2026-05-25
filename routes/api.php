<?php

use App\Models\EmployeeLoan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\OvertimeController;
use App\Http\Controllers\Api\LeaveController;
use App\Http\Controllers\Api\LeaveCreditController;
use App\Http\Controllers\Api\RequisitionController;
use App\Http\Controllers\Api\SecureDocumentController;
use App\Http\Controllers\Api\PayrollController;
use App\Http\Controllers\Api\LoanController;
use App\Http\Controllers\Api\LiquidationController;
use App\Http\Controllers\Api\EmployeeSurveyController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\AdminHrDashboardController;
use App\Http\Controllers\Api\EmployeeDashboardController;
use App\Http\Controllers\Api\AdminAccountingDashboardController;
use App\Http\Controllers\Api\AdminAccountingReportsController;
use App\Http\Controllers\Api\TravelRequestController;
use App\Http\Controllers\Api\TravelLiquidationController;
use App\Http\Controllers\Api\DailyTimeRecordController;
use App\Http\Controllers\Api\SuperAdmin\SuperAdminUserController;




/* =========================
   AUTH
========================= */
Route::middleware('throttle:login')->post('/login', [AuthController::class, 'login']);

/* =========================
   PUBLIC (READ ONLY)
========================= */

Route::post('/payroll', [PayrollController::class, 'store']);
Route::get('/employees', [EmployeeController::class, 'index']);
Route::get('/payroll/export', [PayrollController::class, 'export']);
Route::get('/employees/template', [EmployeeController::class, 'downloadTemplate']);



Broadcast::routes([
   'middleware' => ['auth:sanctum'],
]);

/*
   |--------------------------------------------------------------------------
   | TRAVEL REQUESTS
   |--------------------------------------------------------------------------
   */



Route::middleware('auth:sanctum')->group(function () {

   /*
   |--------------------------------------------------------------------------
   | TRAVEL REQUESTS
   |--------------------------------------------------------------------------
   */
   Route::prefix('travel')->group(function () {

      Route::get(
         '/requests',
         [TravelRequestController::class, 'index']
      );

      Route::post(
         '/requests',
         [TravelRequestController::class, 'store']
      );

      Route::get(
         '/requests/{id}',
         [TravelRequestController::class, 'show']
      );

      Route::post(
         '/requests/approve',
         [TravelRequestController::class, 'approve']
      );

      Route::post(
         '/requests/reject',
         [TravelRequestController::class, 'reject']
      );

      Route::patch(
         '/requests/{id}/complete',
         [TravelRequestController::class, 'complete']
      );

      Route::patch(
         '/requests/{id}/cancel',
         [TravelRequestController::class, 'cancel']
      );



      /*
      |--------------------------------------------------------------------------
      | LIQUIDATIONS
      |--------------------------------------------------------------------------
      */
      Route::get(
         '/liquidations',
         [TravelLiquidationController::class, 'index']
      );

      Route::post(
         '/liquidations',
         [TravelLiquidationController::class, 'store']
      );

      Route::get(
         '/liquidations/{id}',
         [TravelLiquidationController::class, 'show']
      );

      Route::put(
         '/liquidations',
         [TravelLiquidationController::class, 'update']
      );

      Route::patch(
         '/liquidations/{id}/approve',
         [TravelLiquidationController::class, 'approve']
      );

      Route::patch(
         '/liquidations/{id}/reject',
         [TravelLiquidationController::class, 'reject']
      );

   });

});


Route::middleware(['auth:sanctum'])->group(function () {

   Route::prefix('employee-survey')->group(function () {

      Route::get('/employees', [
         EmployeeSurveyController::class,
         'employees'
      ]);

      Route::post('/submit', [
         EmployeeSurveyController::class,
         'submit'
      ]);

      Route::get('/my-submission', [
         EmployeeSurveyController::class,
         'mySubmission'
      ]);

      Route::get('/active-batch', [
         EmployeeSurveyController::class,
         'activeBatch'
      ]);

      Route::get(
         '/batches',
         [EmployeeSurveyController::class, 'batches']
      );

      Route::post(
         '/batches',
         [EmployeeSurveyController::class, 'createBatch']
      );

      Route::put(
         '/batches/{id}/toggle-status',
         [EmployeeSurveyController::class, 'toggleStatus']
      );

      Route::delete(
         '/batches/{id}',
         [EmployeeSurveyController::class, 'destroyBatch']
      );
   });
});

/* =========================
   PROTECTED (ACTIONS)
========================= */
Route::middleware('auth:sanctum')->group(function () {

   Route::post('/change-password', [AuthController::class, 'changePassword']);
   Route::get('/me', [AuthController::class, 'me']);
   Route::post('/logout', [AuthController::class, 'logout']);

   /* =========================
   NOTIFICATIONS
   ========================= */
   Route::get(
      '/notifications',
      [NotificationController::class, 'index']
   );

   Route::get(
      '/notifications/unread-count',
      [NotificationController::class, 'unreadCount']
   );

   Route::patch(
      '/notifications/{id}/read',
      [NotificationController::class, 'markAsRead']
   );

   Route::patch(
      '/notifications/read-all',
      [NotificationController::class, 'markAllAsRead']
   );

   Route::delete(
      '/notifications/{id}',
      [NotificationController::class, 'destroy']
   );

   Route::get(
      '/adminhr/dashboard',
      [AdminHrDashboardController::class, 'index']
   );

   Route::get(
      '/employee/dashboard',
      [EmployeeDashboardController::class, 'index']
   );

   Route::get(
      '/adminaccounting/dashboard',
      [AdminAccountingDashboardController::class, 'index']
   );

   /* =========================
      ACCOUNTING DASHBOARD
   ========================= */

   Route::prefix('accounting')->group(function () {

      Route::get(
         '/requisitions/stats',
         [AdminAccountingDashboardController::class, 'requisitionStats']
      );

      Route::get(
         '/liquidations/stats',
         [AdminAccountingDashboardController::class, 'liquidationStats']
      );

      Route::get(
         '/requisitions/recent',
         [AdminAccountingDashboardController::class, 'recentRequisitions']
      );

      Route::get(
         '/liquidations/recent',
         [AdminAccountingDashboardController::class, 'recentLiquidations']
      );

      /* =========================
         ACCOUNTING REPORTS
      ========================= */

      Route::prefix('reports')->group(function () {

         Route::get(
            '/approved-amounts-by-type',
            [AdminAccountingReportsController::class, 'approvedAmountsByType']
         );

         Route::get(
            '/liquidation-summary',
            [AdminAccountingReportsController::class, 'liquidationSummary']
         );

         Route::get(
            '/monthly-financial-trend',
            [AdminAccountingReportsController::class, 'monthlyFinancialTrend']
         );

         Route::get(
            '/financial-summary',
            [AdminAccountingReportsController::class, 'financialSummary']
         );

      });

   });

   /* =========================
   SUPER ADMIN USERS
========================= */

   Route::prefix('super-admin')->group(function () {

      Route::get(
         '/users',
         [SuperAdminUserController::class, 'index']
      );

      Route::post(
         '/users',
         [SuperAdminUserController::class, 'store']
      );

      Route::put(
         '/users/{id}',
         [SuperAdminUserController::class, 'update']
      );

      Route::delete(
         '/users/{id}',
         [SuperAdminUserController::class, 'destroy']
      );

      Route::patch(
         '/users/{id}/toggle-status',
         [SuperAdminUserController::class, 'toggleStatus']
      );

      Route::post(
         '/users/{id}/reset-password',
         [SuperAdminUserController::class, 'resetPassword']
      );

   });

   // existing routes mo...
   Route::post('/employees', [EmployeeController::class, 'store']);
   Route::put('/employees/{employeeNo}', [EmployeeController::class, 'update']);
   Route::get('/employee/profile', [EmployeeController::class, 'profile']);
   Route::post('/employee/profile/avatar', [EmployeeController::class, 'uploadAvatar']);
   Route::put('/employees/{employeeNo}/toggle-status', [EmployeeController::class, 'toggleStatus']);
   Route::post('/employees/{employeeNo}/send-password', [EmployeeController::class, 'sendPassword']);
   Route::put('/employees/{employeeNo}/toggle-survey-eligibility', [EmployeeController::class, 'toggleSurveyEligibility']);

   Route::get('/employee/requisitions/available-liquidation', [RequisitionController::class, 'availableLiquidation']);

   Route::post('/overtime', [OvertimeController::class, 'store']);
   Route::post('/overtime/{id}/accomplishments', [OvertimeController::class, 'saveAccomplishments']);
   Route::get('/overtime', [OvertimeController::class, 'index']);
   Route::post('/overtime/{id}/pre-approve', [OvertimeController::class, 'preApprove']);
   Route::post('/overtime/{id}/approve', [OvertimeController::class, 'approve']);
   Route::post('/overtime/{id}/reject', [OvertimeController::class, 'reject']);

   /* =========================
   DTR
========================= */

   Route::post(
      '/dtr',
      [DailyTimeRecordController::class, 'store']
   );

   Route::get(
      '/dtr',
      [DailyTimeRecordController::class, 'index']
   );

   Route::put(
      '/dtr/{id}',
      [DailyTimeRecordController::class, 'update']
   );

   Route::put(
      '/dtr/{id}/approve',
      [DailyTimeRecordController::class, 'approve']
   );

   Route::put(
      '/dtr/{id}/reject',
      [DailyTimeRecordController::class, 'reject']
   );

   Route::get(
      '/dtr/export',
      [DailyTimeRecordController::class, 'export']
   );

   Route::post('/leave', [LeaveController::class, 'store']);
   Route::get('/leave', [LeaveController::class, 'index']);
   Route::post('/leave/{id}/approve', [LeaveController::class, 'approve']);
   Route::post('/leave/{id}/reject', [LeaveController::class, 'reject']);
   Route::get('/leave-credits/me', [LeaveCreditController::class, 'myCredits']);

   Route::post('/requisition', [RequisitionController::class, 'store']);
   Route::get('/requisition', [RequisitionController::class, 'index']);
   Route::post('/requisition/{id}/status', [RequisitionController::class, 'updateStatus']);

   Route::get('/payroll', [PayrollController::class, 'index']);
   Route::post('/payroll', [PayrollController::class, 'store']);
   Route::post('/payroll/preview', [PayrollController::class, 'preview']);
   Route::get('/payroll/{id}/payslip', [PayrollController::class, 'downloadPayslip']);
   Route::get('/employees/active', [EmployeeController::class, 'active']);
   Route::get('/employees/export', [EmployeeController::class, 'export']);
   Route::post('/employees/import', [EmployeeController::class, 'import']);


   Route::prefix('liquidations')->middleware('auth:sanctum')->group(function () {

      Route::get('/export', [LiquidationController::class, 'export']);
      Route::get('/', [LiquidationController::class, 'index']);
      Route::post('/', [LiquidationController::class, 'store']);
      Route::get('/{id}', [LiquidationController::class, 'show']);
      Route::put('/{id}/status', [LiquidationController::class, 'updateStatus']);
   });

   Route::apiResource('loans', LoanController::class);
   Route::get('/loans/{id}/schedule', [LoanController::class, 'schedule']);
   Route::get('/loans/{id}/download', [LoanController::class, 'download']);
   Route::get('/employees/{employee}/loans', [LoanController::class, 'employeeLoans']);

   Route::post('/requisition/{id}/attachments', [RequisitionController::class, 'uploadAttachments']);
   Route::delete('/requisition/attachments/{id}', [RequisitionController::class, 'deleteAttachment']);
   Route::get('/requisition/export', [RequisitionController::class, 'export']);

   /* =========================
      SECURE DOCUMENTS (FIXED)
   ========================= */
   Route::prefix('secure-documents')->group(function () {
      Route::get('/', [SecureDocumentController::class, 'index']);
      Route::post('/', [SecureDocumentController::class, 'store']);
      Route::get('/history', [SecureDocumentController::class, 'history']);
      Route::post('/bulk-send', [SecureDocumentController::class, 'bulkSend']);
      Route::post('/send/{id}', [SecureDocumentController::class, 'sendSingle']);
      Route::post('/{id}/resend', [SecureDocumentController::class, 'resend']);
      Route::post('/grouped-send/{id}', [SecureDocumentController::class, 'sendGrouped']);
   });

   Route::get('/logs', [\App\Http\Controllers\Api\LogController::class, 'index']);

});


