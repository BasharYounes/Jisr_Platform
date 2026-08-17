<?php

namespace App\Http\Controllers\Supervisor;

use App\Domains\Supervisor\Actions\GetProjectAssignmentEvaluationsAction;
use App\Domains\Supervisor\Actions\GetProjectAssignmentEvaluationSummaryAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Supervisor\ProjectAssignmentEvaluationResource;
use App\Models\ProjectAssignment;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

final class ProjectAssignmentEvaluationController extends Controller
{
    public function index(
        ProjectAssignment $projectAssignment,
        GetProjectAssignmentEvaluationsAction $action
    ): JsonResponse {
        Gate::authorize('view', $projectAssignment);

        $evaluations = $action->execute($projectAssignment);

        return ApiResponse::success(
            'Project evaluations retrieved successfully',
            ProjectAssignmentEvaluationResource::collection($evaluations)
                ->resolve(request())
        );
    }

    public function summary(
        ProjectAssignment $projectAssignment,
        GetProjectAssignmentEvaluationSummaryAction $action
    ): JsonResponse {
        Gate::authorize('view', $projectAssignment);

        return ApiResponse::success(
            'Project evaluation summary retrieved successfully',
            $action->execute($projectAssignment)
        );
    }
}
