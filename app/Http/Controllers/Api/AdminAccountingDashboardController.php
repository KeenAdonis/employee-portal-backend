<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Models\Requisition;
use App\Models\Liquidation;

use Carbon\Carbon;

class AdminAccountingDashboardController extends Controller
{
    /* =========================================================================
    | REQUISITION STATS
    ========================================================================= */
    public function requisitionStats()
    {
        try {

            return response()->json([

                /*
                |--------------------------------------------------------------------------
                | PENDING REQUISITIONS
                |--------------------------------------------------------------------------
                */
                'pending_requisitions' => Requisition::where(
                    'Status',
                    Requisition::STATUS_PENDING
                )->count(),

                /*
                |--------------------------------------------------------------------------
                | APPROVED THIS MONTH
                |--------------------------------------------------------------------------
                */
                'approved_this_month' => Requisition::where(
                    'Status',
                    Requisition::STATUS_APPROVED
                )
                    ->whereMonth(
                        'ApprovedDate',
                        now()->month
                    )
                    ->whereYear(
                        'ApprovedDate',
                        now()->year
                    )
                    ->count(),
            ]);

        } catch (\Exception $error) {

            return response()->json([

                'success' => false,

                'message' =>
                    'Failed to fetch requisition stats.',

                'error' =>
                    $error->getMessage(),

            ], 500);
        }
    }

    /* =========================================================================
    | LIQUIDATION STATS
    ========================================================================= */
    public function liquidationStats()
    {
        try {

            return response()->json([

                /*
                |--------------------------------------------------------------------------
                | FOR LIQUIDATION
                |--------------------------------------------------------------------------
                | Approved requisitions
                | na wala pang liquidation
                |--------------------------------------------------------------------------
                */
                'for_liquidation' => Requisition::query()

                    ->where(
                        'Status',
                        Requisition::STATUS_APPROVED
                    )

                    ->where(function ($query) {

                        $query->whereNull('has_liquidation')

                            ->orWhere(
                                'has_liquidation',
                                0
                            );
                    })

                    ->count(),

                /*
                |--------------------------------------------------------------------------
                | OVERDUE LIQUIDATIONS
                |--------------------------------------------------------------------------
                */
                'overdue_liquidations' => Requisition::query()

                    ->where(
                        'Status',
                        Requisition::STATUS_APPROVED
                    )

                    ->where(function ($query) {

                        $query->whereNull('has_liquidation')

                            ->orWhere(
                                'has_liquidation',
                                0
                            );
                    })

                    ->whereDate(
                        'ApprovedDate',
                        '<',
                        Carbon::now()->subDays(30)
                    )

                    ->count(),
            ]);

        } catch (\Exception $error) {

            return response()->json([

                'success' => false,

                'message' =>
                    'Failed to fetch liquidation stats.',

                'error' =>
                    $error->getMessage(),

            ], 500);
        }
    }

    /* =========================================================================
    | RECENT REQUISITIONS
    ========================================================================= */
    public function recentRequisitions()
    {
        try {

            $data = Requisition::query()

                ->latest('DateFiled')

                ->take(5)

                ->get([
                    'id',
                    'RequestId',
                    'EmployeeName',
                    'Type',
                    'Status',
                    'DateFiled',
                    'TotalAmount',
                ]);

            return response()->json(
                $data
            );

        } catch (\Exception $error) {

            return response()->json([

                'success' => false,

                'message' =>
                    'Failed to fetch recent requisitions.',

                'error' =>
                    $error->getMessage(),

            ], 500);
        }
    }

    /* =========================================================================
    | RECENT LIQUIDATIONS
    ========================================================================= */
    public function recentLiquidations()
    {
        try {

            $data = Liquidation::query()

                ->with([
                    'requisition',
                ])

                ->latest()

                ->take(5)

                ->get()

                ->map(function ($item) {

                    return [

                        'id' => $item->id,

                        'reference_number' =>
                            $item->request_id,

                        'employee_name' =>
                            optional(
                                $item->requisition
                            )->EmployeeName,

                        'status' =>
                            $item->status,

                        'created_at' =>
                            $item->created_at,

                        'total_expenses' =>
                            $item->total_expenses,
                    ];
                });

            return response()->json(
                $data
            );

        } catch (\Exception $error) {

            return response()->json([

                'success' => false,

                'message' =>
                    'Failed to fetch recent liquidations.',

                'error' =>
                    $error->getMessage(),

            ], 500);
        }
    }
}