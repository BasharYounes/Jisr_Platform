<?php

namespace App\Http\Controllers;

use App\Domains\Supervisor\Actions\ApproveProjectEvaluationAction;
use App\Domains\Supervisor\Actions\SubmitProjectEvaluationAction;
use App\Domains\Supervisor\Requests\SubmitProjectEvaluationRequest;
use App\Http\Resources\ProjectEvaluationResource;
use App\Models\ProjectAssignment;
use App\Models\ProjectEvaluation;
use App\Support\ApiResponse;

class ProjectEvaluationController extends Controller
{
    public function store(
        SubmitProjectEvaluationRequest $request,
        ProjectAssignment $projectAssignment,
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
            $request->validated()
        );

        return ApiResponse::success('Project evaluation submitted successfully',
            new ProjectEvaluationResource($evaluation),
            201
        );
    }

    public function show(ProjectEvaluation $projectEvaluation)
    {
        \Gate::authorize('view', $projectEvaluation);

        $projectEvaluation->load([
            'assignment.students',
            'assignment.projectTemplate',
            'supervisor',
            'items.criteria',
        ]);

        return ApiResponse::success('Project evaluation retrieved successfully',
            new ProjectEvaluationResource($projectEvaluation)
        );
    }

    public function approve(
        ProjectEvaluation $projectEvaluation,
        ApproveProjectEvaluationAction $action
    ) {
        \Gate::authorize('view', $projectEvaluation);

        $approvedEvaluation = $action->execute($projectEvaluation);

        return ApiResponse::success(
            'Project evaluation approved successfully',
            new ProjectEvaluationResource($approvedEvaluation)
        );
    }
}
