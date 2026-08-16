<?php

namespace App\Http\Controllers\Student;

use App\Domains\Student\Actions\CreateProjectEvaluationAppealAction;
use App\Domains\Student\Actions\GetStudentProjectEvaluationByAssignmentAction;
use App\Domains\Student\Actions\ListStudentEvaluationAppealsAction;
use App\Domains\Student\Requests\ListStudentEvaluationAppealsRequest;
use App\Domains\Student\Requests\StoreProjectEvaluationAppealRequest;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectEvaluationResource;
use App\Http\Resources\Student\ProjectEvaluationAppealResource;
use App\Http\Resources\Student\StudentEvaluationAppealListResource;
use App\Models\ProjectAssignment;
use App\Models\ProjectEvaluation;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProjectEvaluationAppealController extends Controller
{
    public function index(
        ListStudentEvaluationAppealsRequest $request,
        ListStudentEvaluationAppealsAction $action
    ): JsonResponse {
        $appeals = $action->execute(
            student: $request->user(),
            filters: $request->validated(),
        );

        return ApiResponse::success(
            'Student evaluation appeals retrieved successfully',
            [
                'appeals' => StudentEvaluationAppealListResource::collection(
                    $appeals->getCollection()
                )->resolve($request),

                'pagination' => [
                    'current_page' =>
                        $appeals->currentPage(),

                    'last_page' =>
                        $appeals->lastPage(),

                    'per_page' =>
                        $appeals->perPage(),

                    'total' =>
                        $appeals->total(),
                ],
            ]
        );
    }

    public function show(
        Request $request,
        ProjectEvaluation $projectEvaluation
    ): JsonResponse {
        Gate::authorize(
            'viewAsStudent',
            $projectEvaluation
        );

        $projectEvaluation->load([
            'assignment.projectTemplate',
            'student',
            'supervisor',
            'items.criteria',
            'items.evidences',

            'appeals' => fn ($query) => $query
                ->where(
                    'student_id',
                    $request->user()->id
                )
                ->latest('id'),
        ]);

        return ApiResponse::success(
            'Project evaluation retrieved successfully',
            [
                'evaluation' => (new ProjectEvaluationResource(
                    $projectEvaluation
                ))->resolve($request),

                'appeal_window' => [
                    'started_at' => $projectEvaluation
                        ->appeal_started_at
                        ?->toISOString(),

                    'deadline_at' => $projectEvaluation
                        ->appeal_deadline_at
                        ?->toISOString(),

                    'is_open' => $projectEvaluation
                        ->isAppealWindowOpen(),

                    'duration_hours' => (int) config(
                        'evaluations.appeal_window_hours',
                        48
                    ),
                ],

                'can_appeal' => Gate::allows(
                    'createAppeal',
                    $projectEvaluation
                ),

                'appeals' => ProjectEvaluationAppealResource::collection(
                    $projectEvaluation->appeals
                )->resolve($request),
            ]
        );
    }

    public function store(
        StoreProjectEvaluationAppealRequest $request,
        ProjectEvaluation $projectEvaluation,
        CreateProjectEvaluationAppealAction $action
    ): JsonResponse {
        /*
         * Authorization only:
         * - caller must be a student
         * - evaluation must belong to the authenticated student
         *
         * Business-rule failures are handled inside the Action and return 422:
         * - pending appeal already exists
         * - evaluation is not submitted
         * - appeal window is not available
         */
        Gate::authorize(
            'submitAppeal',
            $projectEvaluation
        );

        $appeal = $action->execute(
            evaluation: $projectEvaluation,
            student: $request->user(),
            reason: $request->validated('reason'),
        );

        return ApiResponse::success(
            'Project evaluation appeal submitted successfully',
            new ProjectEvaluationAppealResource(
                $appeal
            ),
            201
        );
    }

    public function showByAssignment(
        Request $request,
        ProjectAssignment $projectAssignment,
        GetStudentProjectEvaluationByAssignmentAction $action
    ): JsonResponse {
        Gate::authorize(
            'viewAsStudent',
            $projectAssignment
        );

        $evaluation = $action->execute(
            projectAssignment: $projectAssignment,
            student: $request->user(),
        );

        if ($evaluation === null) {
            return ApiResponse::success(
                'No project evaluation found for this assignment yet',
                [
                    'project_assignment_id' =>
                        $projectAssignment->id,

                    'has_evaluation' => false,

                    'evaluation' => null,

                    'appeal_window' => null,

                    'can_appeal' => false,

                    'appeals' => [],
                ]
            );
        }

        return ApiResponse::success(
            'Project evaluation retrieved successfully',
            [
                'project_assignment_id' =>
                    $projectAssignment->id,

                'has_evaluation' => true,

                'evaluation' =>
                    (new ProjectEvaluationResource(
                        $evaluation
                    ))->resolve($request),

                'appeal_window' => [
                    'started_at' =>
                        $evaluation
                            ->appeal_started_at
                            ?->toISOString(),

                    'deadline_at' =>
                        $evaluation
                            ->appeal_deadline_at
                            ?->toISOString(),

                    'is_open' =>
                        $evaluation
                            ->isAppealWindowOpen(),

                    'duration_hours' =>
                        (int) config(
                            'evaluations.appeal_window_hours',
                            48
                        ),
                ],

                'can_appeal' =>
                    Gate::allows(
                        'createAppeal',
                        $evaluation
                    ),

                'appeals' =>
                    ProjectEvaluationAppealResource::collection(
                        $evaluation->appeals
                    )->resolve($request),
            ]
        );
    }
}
