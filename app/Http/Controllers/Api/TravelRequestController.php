<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;


use App\Http\Requests\Travel\StoreTravelRequest;
use App\Http\Requests\Travel\ApproveTravelRequest;
use App\Http\Requests\Travel\RejectTravelRequest;

use App\Models\TravelRequest;
use App\Models\TravelLog;
use App\Models\Employee;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use Carbon\Carbon;

class TravelRequestController extends Controller
{
    /**
     * =========================================================================
     * INDEX
     * =========================================================================
     */
    public function index(Request $request): JsonResponse
    {
        try {

            $user = auth()->user();

            /*
            |--------------------------------------------------------------------------
            | AUTH CHECK
            |--------------------------------------------------------------------------
            */
            if (!$user) {

                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            /*
            |--------------------------------------------------------------------------
            | QUERY
            |--------------------------------------------------------------------------
            */
            $query = TravelRequest::query()
                ->with([

                    /*
                    |--------------------------------------------------------------------------
                    | EMPLOYEE
                    |--------------------------------------------------------------------------
                    */
                    'employee:employee_id,EmployeeNo,FirstName,MiddleInitial,LastName,Position,ProfileImage',

                    /*
                    |--------------------------------------------------------------------------
                    | APPROVER
                    |--------------------------------------------------------------------------
                    */
                    'approver:employee_id,FirstName,MiddleInitial,LastName',

                    /*
                    |--------------------------------------------------------------------------
                    | OTHER RELATIONS
                    |--------------------------------------------------------------------------
                    */
                    'destinations',
                    'liquidation',
                    'attachments',
                ]);

            /*
            |--------------------------------------------------------------------------
            | ROLE FILTER
            |--------------------------------------------------------------------------
            */
            if ($user->role === 'employee') {

                $query->where(
                    'employee_id',
                    $user->employee_id
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
            | SEARCH FILTER
            |--------------------------------------------------------------------------
            */
            if ($request->filled('search')) {

                $search = $request->search;

                $query->where(function ($q) use ($search) {

                    $q->where(
                        'travel_no',
                        'like',
                        "%{$search}%"
                    )
                        ->orWhere(
                            'destination',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'purpose',
                            'like',
                            "%{$search}%"
                        );
                });
            }

            /*
            |--------------------------------------------------------------------------
            | DATE FILTER
            |--------------------------------------------------------------------------
            */
            if (
                $request->filled('from')
                &&
                $request->filled('to')
            ) {

                $query->whereBetween(
                    'departure_datetime',
                    [
                        $request->from,
                        $request->to
                    ]
                );
            }

            /*
            |--------------------------------------------------------------------------
            | PAGINATION
            |--------------------------------------------------------------------------
            */
            $data = $query
                ->latest()
                ->paginate(
                    $request->per_page ?? 10
                );

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * =========================================================================
     * STORE
     * =========================================================================
     */
    public function store(
        StoreTravelRequest $request
    ): JsonResponse {

        try {

            $travelRequest = DB::transaction(function () use ($request) {

                /*
                |--------------------------------------------------------------------------
                | AUTH USER
                |--------------------------------------------------------------------------
                */
                $user = auth()->user();

                if (!$user) {

                    abort(
                        response()->json([
                            'success' => false,
                            'message' => 'Unauthorized'
                        ], 401)
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | GET EMPLOYEE RECORD
                |--------------------------------------------------------------------------
                */
                $employee = Employee::where(
                    'EmployeeNo',
                    $user->employee_no
                )->first();

                if (!$employee) {

                    abort(
                        response()->json([
                            'success' => false,
                            'message' => 'Employee record not found.'
                        ], 404)
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | TOTAL DAYS
                |--------------------------------------------------------------------------
                */
                $departure = Carbon::parse(
                    $request->departure_datetime
                );

                $return = Carbon::parse(
                    $request->return_datetime
                );

                $totalDays =
                    $departure->diffInDays($return) + 1;

                /*
                |--------------------------------------------------------------------------
                | CREATE TRAVEL REQUEST
                |--------------------------------------------------------------------------
                */
                $travel = TravelRequest::create([

                    'travel_no' =>
                        'TRV-' . now()->format('Y') . '-'
                        . str_pad(
                            (string) random_int(1, 999999),
                            6,
                            '0',
                            STR_PAD_LEFT
                        ),

                    /*
                    |--------------------------------------------------------------------------
                    | EMPLOYEE
                    |--------------------------------------------------------------------------
                    */
                    'employee_id' =>
                        $employee->employee_id,

                    /*
                    |--------------------------------------------------------------------------
                    | DETAILS
                    |--------------------------------------------------------------------------
                    */
                    'destination' =>
                        $request->destination,

                    'purpose' =>
                        $request->purpose,

                    'transportation_type' =>
                        $request->transportation_type,

                    /*
                    |--------------------------------------------------------------------------
                    | PERSONAL VEHICLE
                    |--------------------------------------------------------------------------
                    */
                    'plate_number' =>
                        $request->plate_number,

                    'fuel_consumption' =>
                        $request->fuel_consumption,

                    'fuel_type' =>
                        $request->fuel_type,

                    /*
                    |--------------------------------------------------------------------------
                    | SCHEDULE
                    |--------------------------------------------------------------------------
                    */
                    'departure_datetime' =>
                        $request->departure_datetime,

                    'return_datetime' =>
                        $request->return_datetime,

                    'total_days' =>
                        $totalDays,

                    /*
                    |--------------------------------------------------------------------------
                    | STATUS
                    |--------------------------------------------------------------------------
                    */
                    'status' => 'Pending',
                ]);

                /*
                |--------------------------------------------------------------------------
                | LOG
                |--------------------------------------------------------------------------
                */
                TravelLog::create([

                    'travel_request_id' =>
                        $travel->id,

                    'action' =>
                        'Submitted',

                    'description' =>
                        'Travel request submitted.',

                    'performed_by' =>
                        $employee->employee_id,
                ]);

                return $travel->load([
                    'employee',
                    'attachments',
                    'destinations',
                ]);
            });

            return response()->json([
                'success' => true,

                'message' =>
                    'Travel request submitted successfully.',

                'data' => $travelRequest
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ], 500);
        }
    }

    /**
     * =========================================================================
     * SHOW
     * =========================================================================
     */
    public function show(
        int $id
    ): JsonResponse {

        $travelRequest = TravelRequest::query()
            ->with([
                'employee',
                'approver',
                'destinations',
                'attachments',
                'logs.performer',
                'liquidation.stops',
                'liquidation.attachments',
                'liquidation.logs',
            ])
            ->find($id);

        /*
        |--------------------------------------------------------------------------
        | NOT FOUND
        |--------------------------------------------------------------------------
        */
        if (!$travelRequest) {

            return response()->json([
                'success' => false,
                'message' => 'Travel request not found.'
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
            $travelRequest->employee_id !== $user->employee_id
        ) {

            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $travelRequest
        ]);
    }

    /**
     * =========================================================================
     * APPROVE
     * =========================================================================
     */
    public function approve(
        ApproveTravelRequest $request
    ): JsonResponse {

        try {

            DB::transaction(function () use ($request) {

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
                | PREVENT DOUBLE PROCESSING
                |--------------------------------------------------------------------------
                */
                if ($travel->status !== 'Pending') {

                    throw new \Exception(
                        'Travel request already processed.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | APPROVE
                |--------------------------------------------------------------------------
                */
                $travel->update([

                    'status' => 'approved',

                    'approved_by' =>
                        auth()->user()->employee_id,

                    'approved_at' => now(),
                ]);

                /*
                |--------------------------------------------------------------------------
                | LOG
                |--------------------------------------------------------------------------
                */
                TravelLog::create([
                    'travel_request_id' => $travel->id,

                    'action' => 'approved',

                    'description' =>
                        'Travel request approved.',

                    'performed_by' =>
                        auth()->user()->employee_id,
                ]);
            });

            return response()->json([
                'success' => true,

                'message' =>
                    'Travel request approved successfully.'
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
        RejectTravelRequest $request
    ): JsonResponse {

        try {

            DB::transaction(function () use ($request) {

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
                | PREVENT DOUBLE PROCESSING
                |--------------------------------------------------------------------------
                */
                if ($travel->status !== 'Pending') {

                    throw new \Exception(
                        'Travel request already processed.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | REJECT
                |--------------------------------------------------------------------------
                */
                $travel->update([

                    'status' => 'rejected',

                    'rejection_reason' =>
                        $request->rejection_reason,

                    'approved_by' =>
                        auth()->user()->employee_id,

                    'approved_at' => now(),
                ]);

                /*
                |--------------------------------------------------------------------------
                | LOG
                |--------------------------------------------------------------------------
                */
                TravelLog::create([
                    'travel_request_id' => $travel->id,

                    'action' => 'rejected',

                    'description' =>
                        'Travel request rejected.',

                    'performed_by' =>
                        auth()->user()->employee_id,
                ]);
            });

            return response()->json([
                'success' => true,

                'message' =>
                    'Travel request rejected successfully.'
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
     * COMPLETE
     * =========================================================================
     */
    public function complete(
        int $id
    ): JsonResponse {

        try {

            DB::transaction(function () use ($id) {

                /*
                |--------------------------------------------------------------------------
                | FIND TRAVEL REQUEST
                |--------------------------------------------------------------------------
                */
                $travel = TravelRequest::lockForUpdate()
                    ->findOrFail($id);

                /*
                |--------------------------------------------------------------------------
                | VALIDATE STATUS
                |--------------------------------------------------------------------------
                */
                if ($travel->status !== 'approved') {

                    throw new \Exception(
                        'Only approved travel requests can be completed.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | COMPLETE REQUEST
                |--------------------------------------------------------------------------
                */
                $travel->update([
                    'status' => 'completed',
                ]);

                /*
                |--------------------------------------------------------------------------
                | LOG
                |--------------------------------------------------------------------------
                */
                TravelLog::create([
                    'travel_request_id' => $travel->id,

                    'action' => 'completed',

                    'description' =>
                        'Travel request marked as completed.',

                    'performed_by' =>
                        auth()->user()->employee_id,
                ]);
            });

            return response()->json([
                'success' => true,

                'message' =>
                    'Travel request completed successfully.'
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
     * CANCEL
     * =========================================================================
     */
    public function cancel(
        int $id
    ): JsonResponse {

        try {

            DB::transaction(function () use ($id) {

                /*
                |--------------------------------------------------------------------------
                | FIND TRAVEL REQUEST
                |--------------------------------------------------------------------------
                */
                $travel = TravelRequest::lockForUpdate()
                    ->findOrFail($id);

                /*
                |--------------------------------------------------------------------------
                | VALIDATE STATUS
                |--------------------------------------------------------------------------
                */
                if (
                    !in_array(
                        $travel->status,
                        ['Pending']
                    )
                ) {

                    throw new \Exception(
                        'Only draft or submitted requests can be cancelled.'
                    );
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
                    $travel->employee_id !== $user->employee_id
                ) {

                    throw new \Exception(
                        'Unauthorized action.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | CANCEL REQUEST
                |--------------------------------------------------------------------------
                */
                $travel->update([
                    'status' => 'cancelled',
                ]);

                /*
                |--------------------------------------------------------------------------
                | LOG
                |--------------------------------------------------------------------------
                */
                TravelLog::create([
                    'travel_request_id' => $travel->id,

                    'action' => 'cancelled',

                    'description' =>
                        'Travel request cancelled.',

                    'performed_by' =>
                        auth()->user()->employee_id,
                ]);
            });

            return response()->json([
                'success' => true,

                'message' =>
                    'Travel request cancelled successfully.'
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