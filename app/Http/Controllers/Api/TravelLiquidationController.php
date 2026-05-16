<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Http\Requests\Travel\StoreTravelLiquidationRequest;
use App\Http\Requests\Travel\UpdateTravelLiquidationRequest;

use App\Models\TravelAttachment;
use App\Models\TravelLiquidation;
use App\Models\TravelLiquidationStop;
use App\Models\TravelLog;
use App\Models\TravelRequest;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TravelLiquidationController extends Controller
{
    /**
     * =========================================================================
     * INDEX
     * =========================================================================
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();

        $query = TravelLiquidation::query()
            ->with([
                'travelRequest.employee',
                'approver',
                'stops',
                'attachments',
            ]);

        /*
        |--------------------------------------------------------------------------
        | ROLE FILTER
        |--------------------------------------------------------------------------
        */
        if ($user->role === 'employee') {

            $query->whereHas(
                'travelRequest',
                function ($q) use ($user) {

                    $q->where(
                        'employee_id',
                        $user->employee_id
                    );
                }
            );
        }

        /*
        |--------------------------------------------------------------------------
        | STATUS FILTER
        |--------------------------------------------------------------------------
        */
        if ($request->filled('status')) {

            $statuses = explode(
                ',',
                $request->status
            );

            $query->whereIn(
                'status',
                $statuses
            );
        }

        /*
        |--------------------------------------------------------------------------
        | PAGINATION
        |--------------------------------------------------------------------------
        */
        $liquidations = $query
            ->latest()
            ->paginate(
                $request->per_page ?? 10
            );

        return response()->json([
            'success' => true,
            'data' => $liquidations
        ]);
    }

    /**
     * =========================================================================
     * SHOW
     * =========================================================================
     */
    public function show(
        int $id
    ): JsonResponse {

        $liquidation = TravelLiquidation::query()
            ->with([
                'travelRequest.employee',
                'approver',
                'stops',
                'attachments',
                'logs.performer',
            ])
            ->find($id);

        /*
        |--------------------------------------------------------------------------
        | NOT FOUND
        |--------------------------------------------------------------------------
        */
        if (!$liquidation) {

            return response()->json([
                'success' => false,
                'message' => 'Travel liquidation not found.'
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | ACCESS CONTROL
        |--------------------------------------------------------------------------
        */
        $user = auth()->user();

        if (
            $user->role === 'employee'
            &&
            $liquidation->travelRequest->employee_id
                !== $user->employee_id
        ) {

            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $liquidation
        ]);
    }

    /**
 * =========================================================================
 * STORE
 * =========================================================================
 */
public function store(
    StoreTravelLiquidationRequest $request
): JsonResponse {

    try {

        $liquidation = DB::transaction(
            function () use ($request) {

            /*
            |--------------------------------------------------------------------------
            | FIND TRAVEL REQUEST
            |--------------------------------------------------------------------------
            */
            $travel = TravelRequest::lockForUpdate()
                ->findOrFail(
                    $request->travel_request_id
                );

            /*
            |--------------------------------------------------------------------------
            | VALIDATE STATUS
            |--------------------------------------------------------------------------
            */
            if ($travel->status !== 'completed') {

                throw new \Exception(
                    'Only completed travel requests can be liquidated.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | PREVENT DUPLICATE LIQUIDATION
            |--------------------------------------------------------------------------
            */
            if ($travel->is_liquidated) {

                throw new \Exception(
                    'Travel request already liquidated.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | COMPUTE TOTALS
            |--------------------------------------------------------------------------
            */
            $totalMileage = 0;

            foreach ($request->stops as $stop) {

                $mileage =
                    $stop['odometer_end']
                    -
                    $stop['odometer_start'];

                $totalMileage += $mileage;
            }

            /*
            |--------------------------------------------------------------------------
            | FUEL COMPUTATION
            |--------------------------------------------------------------------------
            */
            $fuelCost = 0;

            if (
                $travel->transportation_type
                === 'personal_vehicle'
            ) {

                $fuelRate = match (
                    $travel->fuel_type
                ) {
                    'diesel' => 65,
                    'premium' => 75,
                    'regular' => 70,
                    default => 0
                };

                $litersUsed =
                    $travel->fuel_consumption > 0
                    ?
                    (
                        $totalMileage
                        /
                        $travel->fuel_consumption
                    )
                    :
                    0;

                $fuelCost =
                    $litersUsed * $fuelRate;
            }

            /*
            |--------------------------------------------------------------------------
            | TOTAL COST
            |--------------------------------------------------------------------------
            */
            $totalCost =
                $fuelCost
                +
                ($request->toll_fee ?? 0)
                +
                ($request->parking_fee ?? 0)
                +
                ($request->other_expenses ?? 0);

            /*
            |--------------------------------------------------------------------------
            | CREATE LIQUIDATION
            |--------------------------------------------------------------------------
            */
            $liquidation =
                TravelLiquidation::create([

                'travel_request_id' =>
                    $travel->id,

                'total_mileage' =>
                    $totalMileage,

                'fuel_cost' =>
                    $fuelCost,

                'toll_fee' =>
                    $request->toll_fee ?? 0,

                'parking_fee' =>
                    $request->parking_fee ?? 0,

                'other_expenses' =>
                    $request->other_expenses ?? 0,

                'total_cost' =>
                    $totalCost,

                'remarks' =>
                    $request->remarks,

                'status' =>
                    'submitted',

                'submitted_at' =>
                    now(),
            ]);

            /*
            |--------------------------------------------------------------------------
            | STOPS
            |--------------------------------------------------------------------------
            */
            foreach ($request->stops as $stop) {

                $mileage =
                    $stop['odometer_end']
                    -
                    $stop['odometer_start'];

                TravelLiquidationStop::create([

                    'travel_liquidation_id' =>
                        $liquidation->id,

                    'from_location' =>
                        $stop['from_location'],

                    'to_location' =>
                        $stop['to_location'],

                    'odometer_start' =>
                        $stop['odometer_start'],

                    'odometer_end' =>
                        $stop['odometer_end'],

                    'mileage' =>
                        $mileage,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | ATTACHMENTS
            |--------------------------------------------------------------------------
            */
            if ($request->hasFile('attachments')) {

                foreach (
                    $request->file('attachments')
                    as $file
                ) {

                    $path = $file->store(
                        'travel-liquidations',
                        'public'
                    );

                    TravelAttachment::create([

                        'travel_liquidation_id' =>
                            $liquidation->id,

                        'file_name' =>
                            $file->getClientOriginalName(),

                        'file_path' =>
                            $path,

                        'file_size' =>
                            $file->getSize(),

                        'mime_type' =>
                            $file->getMimeType(),

                        'uploaded_by' =>
                            auth()->user()->employee_id,
                    ]);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | UPDATE TRAVEL REQUEST
            |--------------------------------------------------------------------------
            */
            $travel->update([
                'is_liquidated' => true,
                'status' => 'liquidated',
            ]);

            /*
            |--------------------------------------------------------------------------
            | LOGS
            |--------------------------------------------------------------------------
            */
            TravelLog::create([

                'travel_request_id' =>
                    $travel->id,

                'travel_liquidation_id' =>
                    $liquidation->id,

                'action' =>
                    'liquidation_submitted',

                'description' =>
                    'Travel liquidation submitted.',

                'performed_by' =>
                    auth()->user()->employee_id,
            ]);

            return $liquidation;
        });

        return response()->json([
            'success' => true,

            'message' =>
                'Travel liquidation submitted successfully.',

            'data' => $liquidation
        ]);

    } catch (\Exception $e) {

        return response()->json([
            'success' => false,

            'message' =>
                $e->getMessage()
        ], 400);
    }
}

/**
 * =========================================================================
 * UPDATE
 * =========================================================================
 */
public function update(
    UpdateTravelLiquidationRequest $request
): JsonResponse {

    try {

        $liquidation = DB::transaction(
            function () use ($request) {

            /*
            |--------------------------------------------------------------------------
            | FIND LIQUIDATION
            |--------------------------------------------------------------------------
            */
            $liquidation =
                TravelLiquidation::lockForUpdate()
                ->with('travelRequest')
                ->findOrFail(
                    $request->travel_liquidation_id
                );

            /*
            |--------------------------------------------------------------------------
            | VALIDATE STATUS
            |--------------------------------------------------------------------------
            */
            if (
                $liquidation->status
                !== 'rejected'
            ) {

                throw new \Exception(
                    'Only rejected liquidations can be updated.'
                );
            }

            $travel = $liquidation->travelRequest;

            /*
            |--------------------------------------------------------------------------
            | RECOMPUTE TOTALS
            |--------------------------------------------------------------------------
            */
            $totalMileage = 0;

            foreach ($request->stops as $stop) {

                $mileage =
                    $stop['odometer_end']
                    -
                    $stop['odometer_start'];

                $totalMileage += $mileage;
            }

            /*
            |--------------------------------------------------------------------------
            | FUEL COMPUTATION
            |--------------------------------------------------------------------------
            */
            $fuelCost = 0;

            if (
                $travel->transportation_type
                === 'personal_vehicle'
            ) {

                $fuelRate = match (
                    $travel->fuel_type
                ) {
                    'diesel' => 65,
                    'premium' => 75,
                    'regular' => 70,
                    default => 0
                };

                $litersUsed =
                    $travel->fuel_consumption > 0
                    ?
                    (
                        $totalMileage
                        /
                        $travel->fuel_consumption
                    )
                    :
                    0;

                $fuelCost =
                    $litersUsed * $fuelRate;
            }

            /*
            |--------------------------------------------------------------------------
            | TOTAL COST
            |--------------------------------------------------------------------------
            */
            $totalCost =
                $fuelCost
                +
                ($request->toll_fee ?? 0)
                +
                ($request->parking_fee ?? 0)
                +
                ($request->other_expenses ?? 0);

            /*
            |--------------------------------------------------------------------------
            | UPDATE LIQUIDATION
            |--------------------------------------------------------------------------
            */
            $liquidation->update([

                'total_mileage' =>
                    $totalMileage,

                'fuel_cost' =>
                    $fuelCost,

                'toll_fee' =>
                    $request->toll_fee ?? 0,

                'parking_fee' =>
                    $request->parking_fee ?? 0,

                'other_expenses' =>
                    $request->other_expenses ?? 0,

                'total_cost' =>
                    $totalCost,

                'remarks' =>
                    $request->remarks,

                'status' =>
                    'submitted',

                'submitted_at' =>
                    now(),
            ]);

            /*
            |--------------------------------------------------------------------------
            | DELETE OLD STOPS
            |--------------------------------------------------------------------------
            */
            $liquidation->stops()->delete();

            /*
            |--------------------------------------------------------------------------
            | RECREATE STOPS
            |--------------------------------------------------------------------------
            */
            foreach ($request->stops as $stop) {

                $mileage =
                    $stop['odometer_end']
                    -
                    $stop['odometer_start'];

                TravelLiquidationStop::create([

                    'travel_liquidation_id' =>
                        $liquidation->id,

                    'from_location' =>
                        $stop['from_location'],

                    'to_location' =>
                        $stop['to_location'],

                    'odometer_start' =>
                        $stop['odometer_start'],

                    'odometer_end' =>
                        $stop['odometer_end'],

                    'mileage' =>
                        $mileage,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | NEW ATTACHMENTS
            |--------------------------------------------------------------------------
            */
            if ($request->hasFile('attachments')) {

                foreach (
                    $request->file('attachments')
                    as $file
                ) {

                    $path = $file->store(
                        'travel-liquidations',
                        'public'
                    );

                    TravelAttachment::create([

                        'travel_liquidation_id' =>
                            $liquidation->id,

                        'file_name' =>
                            $file->getClientOriginalName(),

                        'file_path' =>
                            $path,

                        'file_size' =>
                            $file->getSize(),

                        'mime_type' =>
                            $file->getMimeType(),

                        'uploaded_by' =>
                            auth()->user()->employee_id,
                    ]);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | LOGS
            |--------------------------------------------------------------------------
            */
            TravelLog::create([

                'travel_request_id' =>
                    $travel->id,

                'travel_liquidation_id' =>
                    $liquidation->id,

                'action' =>
                    'liquidation_updated',

                'description' =>
                    'Travel liquidation updated.',

                'performed_by' =>
                    auth()->user()->employee_id,
            ]);

            return $liquidation;
        });

        return response()->json([
            'success' => true,

            'message' =>
                'Travel liquidation updated successfully.',

            'data' => $liquidation
        ]);

    } catch (\Exception $e) {

        return response()->json([
            'success' => false,

            'message' =>
                $e->getMessage()
        ], 400);
    }
}

/**
 * =========================================================================
 * APPROVE
 * =========================================================================
 */
public function approve(
    int $id
): JsonResponse {

    try {

        DB::transaction(function () use ($id) {

            /*
            |--------------------------------------------------------------------------
            | FIND LIQUIDATION
            |--------------------------------------------------------------------------
            */
            $liquidation =
                TravelLiquidation::lockForUpdate()
                ->with('travelRequest')
                ->findOrFail($id);

            /*
            |--------------------------------------------------------------------------
            | VALIDATE STATUS
            |--------------------------------------------------------------------------
            */
            if (
                $liquidation->status
                !== 'submitted'
            ) {

                throw new \Exception(
                    'Travel liquidation already processed.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | APPROVE LIQUIDATION
            |--------------------------------------------------------------------------
            */
            $liquidation->update([

                'status' =>
                    'approved',

                'approved_by' =>
                    auth()->user()->employee_id,

                'approved_at' =>
                    now(),
            ]);

            /*
            |--------------------------------------------------------------------------
            | UPDATE TRAVEL REQUEST
            |--------------------------------------------------------------------------
            */
            $liquidation->travelRequest
                ->update([
                    'status' => 'closed',
                ]);

            /*
            |--------------------------------------------------------------------------
            | LOGS
            |--------------------------------------------------------------------------
            */
            TravelLog::create([

                'travel_request_id' =>
                    $liquidation->travel_request_id,

                'travel_liquidation_id' =>
                    $liquidation->id,

                'action' =>
                    'liquidation_approved',

                'description' =>
                    'Travel liquidation approved.',

                'performed_by' =>
                    auth()->user()->employee_id,
            ]);
        });

        return response()->json([
            'success' => true,

            'message' =>
                'Travel liquidation approved successfully.'
        ]);

    } catch (\Exception $e) {

        return response()->json([
            'success' => false,

            'message' =>
                $e->getMessage()
        ], 400);
    }
}

/**
 * =========================================================================
 * REJECT
 * =========================================================================
 */
public function reject(
    Request $request,
    int $id
): JsonResponse {

    $request->validate([
        'reason' => [
            'required',
            'string',
            'max:1000'
        ]
    ]);

    try {

        DB::transaction(function () use ($id, $request) {

            /*
            |--------------------------------------------------------------------------
            | FIND LIQUIDATION
            |--------------------------------------------------------------------------
            */
            $liquidation =
                TravelLiquidation::lockForUpdate()
                ->with('travelRequest')
                ->findOrFail($id);

            /*
            |--------------------------------------------------------------------------
            | VALIDATE STATUS
            |--------------------------------------------------------------------------
            */
            if (
                $liquidation->status
                !== 'submitted'
            ) {

                throw new \Exception(
                    'Travel liquidation already processed.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | REJECT LIQUIDATION
            |--------------------------------------------------------------------------
            */
            $liquidation->update([

                'status' =>
                    'rejected',

                'remarks' =>
                    $request->reason,

                'approved_by' =>
                    auth()->user()->employee_id,

                'approved_at' =>
                    now(),
            ]);

            /*
            |--------------------------------------------------------------------------
            | REVERT TRAVEL STATUS
            |--------------------------------------------------------------------------
            */
            $liquidation->travelRequest
                ->update([
                    'status' => 'completed',
                ]);

            /*
            |--------------------------------------------------------------------------
            | LOGS
            |--------------------------------------------------------------------------
            */
            TravelLog::create([

                'travel_request_id' =>
                    $liquidation->travel_request_id,

                'travel_liquidation_id' =>
                    $liquidation->id,

                'action' =>
                    'liquidation_rejected',

                'description' =>
                    'Travel liquidation rejected.',

                'performed_by' =>
                    auth()->user()->employee_id,
            ]);
        });

        return response()->json([
            'success' => true,

            'message' =>
                'Travel liquidation rejected successfully.'
        ]);

    } catch (\Exception $e) {

        return response()->json([
            'success' => false,

            'message' =>
                $e->getMessage()
        ], 400);
    }
}

}