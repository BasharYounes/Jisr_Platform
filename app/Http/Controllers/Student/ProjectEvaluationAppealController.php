<?php

namespace App\Http\Controllers\Student;

use App\Domains\Student\Actions\CreateProjectEvaluationAppealAction;
use App\Domains\Student\Requests\StoreProjectEvaluationAppealRequest;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectEvaluationResource;
use App\Http\Resources\Student\ProjectEvaluationAppealResource;
use App\Models\ProjectEvaluation;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use App\Domains\Student\Actions\GetStudentProjectEvaluationByAssignmentAction;
use App\Models\ProjectAssignment;

class ProjectEvaluationAppealController extends Controller
{
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
        Gate::authorize(
            'createAppeal',
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

        /*
        * عدم وجود تقييم ليس Error.
        *
        * هذا يعني أن الطالب لم يتم تقييمه بعد،
        * وبالتالي يستطيع الفرونت متابعة الـFlow القديم
        * الخاص بالتسليم.
        */
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

                /*
                * هذه أهم قيمة للFrontend.
                *
                * لا نتركه يعيد كتابة Business Rules.
                * Backend نفسه يقرر هل الطالب يستطيع
                * الاعتراض أم لا.
                */
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
