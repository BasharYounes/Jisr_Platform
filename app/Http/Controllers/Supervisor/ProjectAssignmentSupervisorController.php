<?php

namespace App\Http\Controllers\Supervisor;

use App\Domains\Supervisor\Actions\ChangeProjectAssignmentSupervisorAction;
use App\Domains\Supervisor\Requests\ChangeProjectAssignmentSupervisorRequest;
use App\Http\Controllers\Controller;
use App\Models\ProjectAssignment;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ProjectAssignmentSupervisorController extends Controller
{
    public function update(
        ChangeProjectAssignmentSupervisorRequest $request,
        ProjectAssignment $projectAssignment,
        ChangeProjectAssignmentSupervisorAction $action
    ): JsonResponse {
        Gate::authorize(
            'changeSupervisor',
            $projectAssignment
        );

        $validated = $request->validated();

        $result = $action->execute(
            projectAssignment: $projectAssignment,
            changedBy: $request->user(),
            newSupervisorId: (int) $validated['new_supervisor_id'],
            reason: $validated['reason'],
        );

        return ApiResponse::success(
            'Project assignment supervisor changed successfully',
            $result
        );
    }
}
