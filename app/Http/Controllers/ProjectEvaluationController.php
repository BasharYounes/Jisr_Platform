<?php

namespace App\Http\Controllers;

use App\Domains\Supervisor\Actions\ApproveProjectEvaluationAction;
use App\Domains\Supervisor\Actions\SubmitProjectEvaluationAction;
use App\Domains\Supervisor\Requests\SubmitProjectEvaluationRequest;
use App\Http\Resources\ProjectEvaluationResource;
use App\Models\ProjectAssignment;
use App\Models\ProjectEvaluation;
use App\Models\User;
use App\Support\ApiResponse;
use App\Domains\Supervisor\Actions\RequestProjectEvaluationRevisionAction;
use App\Domains\Supervisor\Requests\StoreEvaluationRevisionRequest;
use App\Http\Resources\Supervisor\EvaluationRevisionRequestResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use App\Domains\Supervisor\Actions\ResubmitProjectEvaluationAction;
use App\Domains\Supervisor\Actions\UpdateProjectEvaluationAction;
use App\Domains\Supervisor\Requests\ResubmitProjectEvaluationRequest;
use App\Domains\Supervisor\Requests\UpdateProjectEvaluationRequest;

class ProjectEvaluationController extends Controller
{
    public function store(
        SubmitProjectEvaluationRequest $request,
        ProjectAssignment $projectAssignment,
        User $student,
        SubmitProjectEvaluationAction $action
    ) {
        \Gate::authorize('create', ProjectEvaluation::class);

        abort_if(
            $projectAssignment->supervisor_id !== auth()->id(),
            403,
            'You are not allowed to evaluate this project assignment.'
        );

        $evaluation = $action->execute(
            $projectAssignment,
            $student,
            $request->validated()
        );

        return ApiResponse::success(
            'Student project evaluation submitted successfully',
            new ProjectEvaluationResource($evaluation),
            201
        );
    }

    public function show(ProjectEvaluation $projectEvaluation)
    {
        \Gate::authorize('view', $projectEvaluation);

        $projectEvaluation->load([
            'assignment.projectTemplate',
            'student',
            'supervisor',
            'items.criteria',
            'items.evidences',
        ]);

        return ApiResponse::success(
            'Project evaluation retrieved successfully',
            new ProjectEvaluationResource($projectEvaluation)
        );
    }

    public function approve(
        ProjectEvaluation $projectEvaluation,
        ApproveProjectEvaluationAction $action
    ) {
        \Gate::authorize('approve', [$projectEvaluation]);

        $approvedEvaluation = $action->execute($projectEvaluation);

        return ApiResponse::success(
            'Project evaluation approved successfully',
            new ProjectEvaluationResource($approvedEvaluation)
        );
    }

    public function requestRevision(
        StoreEvaluationRevisionRequest $request,
        ProjectEvaluation $projectEvaluation,
        RequestProjectEvaluationRevisionAction $action
    ): JsonResponse {
        Gate::authorize(
            'requestRevision',
            $projectEvaluation
        );

        $revisionRequest = $action->execute(
            evaluation: $projectEvaluation,
            requestedBy: $request->user(),
            reason: $request->validated('reason'),
        );

        $updatedEvaluation =
            $projectEvaluation
                ->refresh()
                ->load([
                    'assignment.projectTemplate',
                    'student',
                    'supervisor',
                    'items.criteria',
                    'items.evidences',
                ]);

        return ApiResponse::success(
            'Project evaluation returned for revision successfully',
            [
                'evaluation' =>
                    (new ProjectEvaluationResource(
                        $updatedEvaluation
                    ))->resolve($request),

                'revision_request' =>
                    (new EvaluationRevisionRequestResource(
                        $revisionRequest
                    ))->resolve($request),
            ],
            201
        );
    }

    public function update(
        UpdateProjectEvaluationRequest $request,
        ProjectEvaluation $projectEvaluation,
        UpdateProjectEvaluationAction $action
    ): JsonResponse {
        Gate::authorize(
            'update',
            $projectEvaluation
        );

        $updatedEvaluation = $action->execute(
            evaluation: $projectEvaluation,
            data: $request->validated(),
        );

        return ApiResponse::success(
            'Project evaluation updated successfully',
            new ProjectEvaluationResource(
                $updatedEvaluation
            )
        );
    }

    public function resubmit(
        ResubmitProjectEvaluationRequest $request,
        ProjectEvaluation $projectEvaluation,
        ResubmitProjectEvaluationAction $action
    ): JsonResponse {
        Gate::authorize(
            'resubmit',
            $projectEvaluation
        );

        $result = $action->execute(
            evaluation: $projectEvaluation,
            supervisor: $request->user(),
            resolutionNote:
                $request->validated(
                    'resolution_note'
                ),
        );

        return ApiResponse::success(
            'Project evaluation resubmitted successfully',
            [
                'evaluation' =>
                    (new ProjectEvaluationResource(
                        $result['evaluation']
                    ))->resolve($request),

                'revision_request' =>
                    (new EvaluationRevisionRequestResource(
                        $result['revision_request']
                    ))->resolve($request),
            ]
        );
    }
}
