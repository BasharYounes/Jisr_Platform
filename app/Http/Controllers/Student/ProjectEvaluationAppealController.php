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
}
