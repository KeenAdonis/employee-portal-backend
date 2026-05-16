<?php

namespace App\Http\Controllers\Api;

use Carbon\Carbon;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\EmployeeSurvey;
use App\Models\EmployeeSurveyBatch;
use App\Models\EmployeeSurveyRanking;
use App\Models\Notification;
use App\Events\NotificationCreated;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class EmployeeSurveyController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | GET EMPLOYEES FOR SURVEY
    |--------------------------------------------------------------------------
    */

    public function employees()
    {
        try {

            $user = auth()->user();

            if (!$user) {

                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            /*
            |--------------------------------------------------------------------------
            | GET LOGGED EMPLOYEE
            |--------------------------------------------------------------------------
            */

            $loggedEmployee = Employee::where(
                'EmployeeNo',
                $user->EmployeeNo ?? $user->employee_no
            )->first();

            if (!$loggedEmployee) {

                return response()->json([
                    'success' => false,
                    'message' => 'Employee record not found.'
                ], 404);
            }

            $employees = Employee::query()

                ->where(
                    'employee_id',
                    '!=',
                    $loggedEmployee->employee_id
                )

                ->where('Status', 'ACTIVE')
                ->where('IsSurveyExcluded', false)

                ->where(
                    'Company',
                    $loggedEmployee->Company
                )

                ->selectRaw('
                    employee_id as id,
                    EmployeeNo,
                    FirstName as firstname,
                    LastName as lastname,
                    Department as department,
                    Position as position
                ')

                ->orderBy('FirstName')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $employees
            ]);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch employees.',
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | GET ACTIVE BATCH
    |--------------------------------------------------------------------------
    */

    public function activeBatch()
    {
        try {

            $user = auth()->user();

            if (!$user) {

                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            /*
            |--------------------------------------------------------------------------
            | GET LOGGED EMPLOYEE
            |--------------------------------------------------------------------------
            */

            $loggedEmployee = Employee::where(
                'EmployeeNo',
                $user->EmployeeNo ?? $user->employee_no
            )->first();

            if (!$loggedEmployee) {

                return response()->json([
                    'success' => false,
                    'message' => 'Employee record not found.'
                ], 404);
            }

            /*
            |--------------------------------------------------------------------------
            | GET ACTIVE BATCH FOR COMPANY
            |--------------------------------------------------------------------------
            */

            $batch = EmployeeSurveyBatch::query()

                ->where('is_active', true)

                ->where(
                    'company',
                    $loggedEmployee->Company
                )

                ->latest()

                ->first();

            return response()->json([
                'success' => true,
                'data' => $batch
            ]);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch active survey batch.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SUBMIT SURVEY
    |--------------------------------------------------------------------------
    */

    public function submit(Request $request)
    {
        $user = auth()->user();

        if (!$user) {

            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        /*
        |--------------------------------------------------------------------------
        | GET LOGGED EMPLOYEE
        |--------------------------------------------------------------------------
        */

        $loggedEmployee = Employee::where(
            'EmployeeNo',
            $user->EmployeeNo ?? $user->employee_no
        )->first();

        if (!$loggedEmployee) {

            return response()->json([
                'success' => false,
                'message' => 'Employee record not found.'
            ], 404);
        }

        $validator = Validator::make($request->all(), [

            'batch_id' => [
                'required',
                'exists:tb_employee_survey_batches,id'
            ],

            'top_reason' => [
                'required',
                'string',
                'max:5000'
            ],

            'bottom_reason' => [
                'required',
                'string',
                'max:5000'
            ],

            'rankings' => [
                'required',
                'array',
                'min:1'
            ],

            'rankings.*.employee_id' => [
                'required',
                'exists:tb_employee_list,employee_id'
            ],

            'rankings.*.rank_position' => [
                'required',
                'integer',
                'min:1'
            ],

        ]);

        if ($validator->fails()) {

            return response()->json([
                'success' => false,
                'message' => 'Validation error.',
                'errors' => $validator->errors()
            ], 422);
        }



        try {

            $batch = EmployeeSurveyBatch::find($request->batch_id);

            /*
            |--------------------------------------------------------------------------
            | VALIDATE COMPANY MATCH
            |--------------------------------------------------------------------------
            */

            if (
                !$batch ||
                $batch->company !==
                $loggedEmployee->Company
            ) {

                return response()->json([
                    'success' => false,
                    'message' => 'You are not allowed to participate in this survey.'
                ], 403);
            }

            if (!$batch || !$batch->is_active) {

                return response()->json([
                    'success' => false,
                    'message' => 'Survey batch is not active.'
                ], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | PREVENT MULTIPLE SUBMISSION
            |--------------------------------------------------------------------------
            */

            $existingSubmission = EmployeeSurvey::query()

                ->where('batch_id', $request->batch_id)

                ->where(
                    'evaluator_employee_id',
                    $loggedEmployee->employee_id
                )

                ->exists();

            if ($existingSubmission) {

                return response()->json([
                    'success' => false,
                    'message' => 'You already submitted this survey.'
                ], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | RANKINGS
            |--------------------------------------------------------------------------
            */

            $rankings = collect($request->rankings);

            $employeeIds = $rankings
                ->pluck('employee_id');

            /*
            |--------------------------------------------------------------------------
            | VALIDATE COMPANY EMPLOYEES
            |--------------------------------------------------------------------------
            */

            $validEmployeeCount = Employee::query()

                ->whereIn(
                    'employee_id',
                    $employeeIds
                )

                ->where(
                    'Company',
                    $loggedEmployee->Company
                )

                ->count();

            if (
                $validEmployeeCount !==
                $employeeIds->count()
            ) {

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid employee rankings detected.'
                ], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | VALIDATE SELF RANKING
            |--------------------------------------------------------------------------
            */

            if (
                $employeeIds->contains(
                    $loggedEmployee->employee_id
                )
            ) {

                return response()->json([
                    'success' => false,
                    'message' => 'You cannot rank yourself.'
                ], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | SORT RANKINGS
            |--------------------------------------------------------------------------
            */

            $sortedRankings = $rankings
                ->sortBy('rank_position')
                ->values();

            $topEmployeeId =
                $sortedRankings->first()['employee_id'];

            $bottomEmployeeId =
                $sortedRankings->last()['employee_id'];

            DB::beginTransaction();

            /*
            |--------------------------------------------------------------------------
            | CREATE SURVEY
            |--------------------------------------------------------------------------
            */

            $survey = EmployeeSurvey::create([

                'batch_id' => $request->batch_id,

                'evaluator_employee_id' =>
                    $loggedEmployee->employee_id,

                'top_employee_id' => $topEmployeeId,

                'bottom_employee_id' => $bottomEmployeeId,

                'top_reason' => $request->top_reason,

                'bottom_reason' => $request->bottom_reason,

                'submitted_at' => Carbon::now(),
            ]);

            /*
            |--------------------------------------------------------------------------
            | SAVE RANKINGS
            |--------------------------------------------------------------------------
            */

            $totalEmployees = $rankings->count();

            foreach ($sortedRankings as $ranking) {

                $score =
                    ($totalEmployees - $ranking['rank_position']) + 1;

                EmployeeSurveyRanking::create([

                    'employee_survey_id' => $survey->id,

                    'ranked_employee_id' =>
                        $ranking['employee_id'],

                    'rank_position' =>
                        $ranking['rank_position'],

                    'score' => $score,
                ]);
            }

            /* =========================
               NOTIFY HR
            ========================= */
            $hrUsers = User::query()
                ->where('role', 'adminhr')
                ->get();

            foreach ($hrUsers as $hr) {

                $notification = Notification::create([
                    'user_id' => $hr->id,

                    'type' => 'employee_survey',

                    'title' => 'New Employee Survey Submission',

                    'message' =>
                        $loggedEmployee->FirstName . ' ' .
                        $loggedEmployee->LastName .
                        ' submitted an employee survey.',

                    'related_type' => 'employee_survey',

                    'related_id' => $survey->id,

                    'action_url' => '/dashboard/adminhr/employee-survey',
                ]);

                event(
                    new NotificationCreated(
                        $notification
                    )
                );
            }


            DB::commit();


            return response()->json([
                'success' => true,
                'message' => 'Survey submitted successfully.'
            ]);

        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit survey.',
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | CHECK MY SUBMISSION
    |--------------------------------------------------------------------------
    */

    public function mySubmission(Request $request)
    {
        try {

            $user = auth()->user();

            $loggedEmployee = Employee::where(
                'EmployeeNo',
                $user->EmployeeNo ?? $user->employee_no
            )->first();

            if (!$loggedEmployee) {

                return response()->json([
                    'success' => false,
                    'message' => 'Employee record not found.'
                ], 404);
            }

            $survey = EmployeeSurvey::with([
                'rankings.rankedEmployee',
                'topEmployee',
                'bottomEmployee',
            ])

                ->where(
                    'batch_id',
                    $request->batch_id
                )

                ->where(
                    'evaluator_employee_id',
                    $loggedEmployee->employee_id
                )

                ->first();

            return response()->json([
                'success' => true,
                'data' => $survey
            ]);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch submission.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function batches(Request $request)
    {
        $search = $request->search;

        $batches = EmployeeSurveyBatch::query()

            ->when($search, function ($query) use ($search) {

                $query->where(
                    'name',
                    'like',
                    "%{$search}%"
                );
            })

            ->latest()

            ->get()

            ->map(function ($batch) {

                $totalEmployees =
                    Employee::query()

                        ->where(
                            'Status',
                            'ACTIVE'
                        )

                        ->where(
                            'Company',
                            $batch->company
                        )

                        ->count();

                $totalSubmitted =
                    EmployeeSurvey::query()

                        ->where(
                            'batch_id',
                            $batch->id
                        )

                        ->distinct(
                            'evaluator_employee_id'
                        )

                        ->count(
                            'evaluator_employee_id'
                        );

                $topEmployees =
                    EmployeeSurveyRanking::query()

                        ->selectRaw('
            ranked_employee_id,
            SUM(score) as total_score
        ')

                        ->whereHas('survey', function ($query) use ($batch) {

                            $query->where(
                                'batch_id',
                                $batch->id
                            );
                        })

                        ->groupBy('ranked_employee_id')

                        ->orderByDesc('total_score')

                        ->take(10)

                        ->with('rankedEmployee')

                        ->get()

                        ->map(function ($ranking) use ($batch) {

                            $employeeId =
                                $ranking->ranked_employee_id;

                            /*
                            |--------------------------------------------------------------------------
                            | POSITIVE COMMENTS
                            |--------------------------------------------------------------------------
                            */

                            $positiveComments =
                                EmployeeSurvey::query()

                                    ->where(
                                        'batch_id',
                                        $batch->id
                                    )

                                    ->where(
                                        'top_employee_id',
                                        $employeeId
                                    )

                                    ->pluck('top_reason')

                                    ->filter()

                                    ->values();

                            /*
                            |--------------------------------------------------------------------------
                            | NEGATIVE COMMENTS
                            |--------------------------------------------------------------------------
                            */

                            $negativeComments =
                                EmployeeSurvey::query()

                                    ->where(
                                        'batch_id',
                                        $batch->id
                                    )

                                    ->where(
                                        'bottom_employee_id',
                                        $employeeId
                                    )

                                    ->pluck('bottom_reason')

                                    ->filter()

                                    ->values();

                            return [

                                'name' =>
                                    $ranking->rankedEmployee
                                    ? $ranking->rankedEmployee->FirstName . ' ' .
                                    $ranking->rankedEmployee->LastName
                                    : '-',

                                'department' =>
                                    $ranking->rankedEmployee->Department ?? '-',

                                'score' =>
                                    $ranking->total_score,

                                'positive_comments' =>
                                    $positiveComments,

                                'negative_comments' =>
                                    $negativeComments,
                            ];
                        });

                return [

                    'id' => $batch->id,

                    'name' => $batch->name,

                    'description' => $batch->description,

                    'company' => $batch->company,

                    'start_date' =>
                        optional($batch->start_date)
                            ->format('Y-m-d'),

                    'end_date' =>
                        optional($batch->end_date)
                            ->format('Y-m-d'),

                    'is_active' => $batch->is_active,

                    'total_employees' =>
                        $totalEmployees,

                    'total_submitted' =>
                        $totalSubmitted,

                    'total_pending' =>
                        $totalEmployees - $totalSubmitted,

                    'top_employees' =>
                        $topEmployees,

                    'participants' => [],
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $batches,
        ]);
    }

    public function toggleStatus($id)
    {
        $batch = EmployeeSurveyBatch::findOrFail($id);

        /*
        |--------------------------------------------------------------------------
        | SINGLE ACTIVE BATCH
        |--------------------------------------------------------------------------
        */

        if (!$batch->is_active) {

            EmployeeSurveyBatch::query()

                ->where(
                    'company',
                    $batch->company
                )

                ->update([
                    'is_active' => false
                ]);
        }

        $batch->update([
            'is_active' => !$batch->is_active
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Batch updated successfully.'
        ]);
    }

    /*
|--------------------------------------------------------------------------
| CREATE SURVEY BATCH
|--------------------------------------------------------------------------
*/

    public function createBatch(Request $request)
    {
        $validator = Validator::make($request->all(), [

            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'company' => [
                'required',
                Rule::in([
                    'Psy Systems and Innovations, OPC',
                    'Pillars Psychological Services',
                ]),
            ],

            'start_date' => [
                'required',
                'date',
            ],

            'end_date' => [
                'required',
                'date',
                'after_or_equal:start_date',
            ],

            'is_active' => [
                'required',
                'boolean',
            ],

        ]);

        if ($validator->fails()) {

            return response()->json([
                'success' => false,
                'message' => 'Validation error.',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {

            /*
            |--------------------------------------------------------------------------
            | AUTO DEACTIVATE OTHER ACTIVE BATCHES
            |--------------------------------------------------------------------------
            */

            if ($request->is_active) {

                EmployeeSurveyBatch::query()

                    ->where('is_active', true)

                    ->where(
                        'company',
                        $request->company
                    )

                    ->update([
                        'is_active' => false
                    ]);
            }

            /*
            |--------------------------------------------------------------------------
            | CREATE BATCH
            |--------------------------------------------------------------------------
            */

            $batch = EmployeeSurveyBatch::create([

                'name' => $request->name,

                'description' => $request->description,

                'company' => $request->company,

                'start_date' => $request->start_date,

                'end_date' => $request->end_date,

                'is_active' => $request->is_active,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Survey batch created successfully.',
                'data' => $batch,
            ]);

        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create survey batch.',
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    public function destroyBatch($id)
    {
        try {

            $batch = EmployeeSurveyBatch::findOrFail($id);

            $batch->delete();

            return response()->json([
                'success' => true,
                'message' => 'Survey batch deleted successfully.',
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);

        }
    }
}