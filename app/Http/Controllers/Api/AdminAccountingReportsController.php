<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Models\Requisition;
use App\Models\Liquidation;

use Illuminate\Support\Facades\DB;

class AdminAccountingReportsController extends Controller
{
    /* =========================================================================
    | APPROVED AMOUNTS BY TYPE
    ========================================================================= */
    public function approvedAmountsByType()
    {
        try {

            $currentMonth = now()->month;

            $currentYear = now()->year;

            $statuses = [

                Requisition::STATUS_APPROVED,

                Requisition::STATUS_LIQUIDATED,

            ];

            $data = [

                'cash_advance' => Requisition::query()

                    ->whereIn(
                        'Status',
                        $statuses
                    )

                    ->where(
                        'Type',
                        'Cash Advance'
                    )

                    ->whereMonth(
                        'ApprovedDate',
                        $currentMonth
                    )

                    ->whereYear(
                        'ApprovedDate',
                        $currentYear
                    )

                    ->sum('TotalAmount'),

                'petty_cash' => Requisition::query()

                    ->whereIn(
                        'Status',
                        $statuses
                    )

                    ->where(
                        'Type',
                        'Petty Cash'
                    )

                    ->whereMonth(
                        'ApprovedDate',
                        $currentMonth
                    )

                    ->whereYear(
                        'ApprovedDate',
                        $currentYear
                    )

                    ->sum('TotalAmount'),

                'reimbursement' => Requisition::query()

                    ->whereIn(
                        'Status',
                        $statuses
                    )

                    ->where(
                        'Type',
                        'Reimbursement'
                    )

                    ->whereMonth(
                        'ApprovedDate',
                        $currentMonth
                    )

                    ->whereYear(
                        'ApprovedDate',
                        $currentYear
                    )

                    ->sum('TotalAmount'),

                'request_for_payment' => Requisition::query()

                    ->whereIn(
                        'Status',
                        $statuses
                    )

                    ->where(
                        'Type',
                        'Request for Payment'
                    )

                    ->whereMonth(
                        'ApprovedDate',
                        $currentMonth
                    )

                    ->whereYear(
                        'ApprovedDate',
                        $currentYear
                    )

                    ->sum('TotalAmount'),

            ];

            return response()->json(
                $data
            );

        } catch (\Exception $error) {

            return response()->json([

                'success' => false,

                'message' =>
                    'Failed to fetch approved amounts.',

                'error' =>
                    $error->getMessage(),

            ], 500);
        }
    }

    /* =========================================================================
    | LIQUIDATION SUMMARY
    ========================================================================= */
    public function liquidationSummary()
    {
        try {

            $data = [

                'total_expenses' => Liquidation::sum(
                    'total_expenses'
                ),

                'total_returned' => Liquidation::sum(
                    'amount_returned'
                ),

                'total_reimbursement' => Liquidation::sum(
                    'amount_reimbursement'
                ),

                'pending_liquidations' => Liquidation::where(
                    'status',
                    'Pending'
                )->count(),

            ];

            return response()->json(
                $data
            );

        } catch (\Exception $error) {

            return response()->json([

                'success' => false,

                'message' =>
                    'Failed to fetch liquidation summary.',

                'error' =>
                    $error->getMessage(),

            ], 500);
        }
    }

    /* =========================================================================
    | MONTHLY FINANCIAL TREND
    ========================================================================= */
    public function monthlyFinancialTrend()
    {
        try {

            $statuses = [

                Requisition::STATUS_APPROVED,

                Requisition::STATUS_LIQUIDATED,

            ];

            $months = collect([

                'Jan',
                'Feb',
                'Mar',
                'Apr',
                'May',
                'Jun',
                'Jul',
                'Aug',
                'Sep',
                'Oct',
                'Nov',
                'Dec',

            ]);

            $data = $months->map(function (
                $month,
                $index
            ) use ($statuses) {

                $monthNumber = $index + 1;

                $amount = Requisition::query()

                    ->whereIn(
                        'Status',
                        $statuses
                    )

                    ->whereMonth(
                        'ApprovedDate',
                        $monthNumber
                    )

                    ->whereYear(
                        'ApprovedDate',
                        now()->year
                    )

                    ->sum('TotalAmount');

                return [

                    'month' => $month,

                    'amount' => (float) $amount,

                ];
            });

            return response()->json(
                $data
            );

        } catch (\Exception $error) {

            return response()->json([

                'success' => false,

                'message' =>
                    'Failed to fetch monthly trend.',

                'error' =>
                    $error->getMessage(),

            ], 500);
        }
    }

    /* =========================================================================
    | FINANCIAL SUMMARY
    ========================================================================= */
    public function financialSummary()
    {
        try {

            $data = Requisition::query()

                ->select(

                    'Type',

                    DB::raw(
                        'COUNT(*) as total_requests'
                    ),

                    DB::raw(
                        'SUM(TotalAmount) as approved_amount'
                    )

                )

                ->whereIn('Status', [

                    Requisition::STATUS_APPROVED,

                    Requisition::STATUS_LIQUIDATED,

                ])

                ->groupBy('Type')

                ->get()

                ->map(function ($item) {

                    return [

                        'type' =>
                            $item->Type,

                        'total_requests' =>
                            $item->total_requests,

                        'approved_amount' =>
                            (float) $item->approved_amount,

                    ];
                });

            return response()->json(
                $data
            );

        } catch (\Exception $error) {

            return response()->json([

                'success' => false,

                'message' =>
                    'Failed to fetch financial summary.',

                'error' =>
                    $error->getMessage(),

            ], 500);
        }
    }
}