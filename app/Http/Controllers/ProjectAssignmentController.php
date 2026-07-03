<?php

namespace App\Http\Controllers;

use App\Domains\Student\Actions\StartAssignmentTaskAction;
use App\Domains\Student\Actions\SubmitAssignmentTaskAction;
use App\Domains\Student\Requests\SubmitAssignmentTaskRequest;
use App\Domains\Supervisor\Actions\ApproveAssignmentTaskAction;
use App\Domains\Supervisor\Actions\AssignAssignmentTaskToStudentAction;
use App\Domains\Supervisor\Actions\AssignProjectAction;
use App\Domains\Supervisor\Actions\RecalculateProjectAssignmentProgressAction;
use App\Domains\Supervisor\Actions\RequestAssignmentTaskRevisionAction;
use App\Domains\Supervisor\Actions\StartAssignmentTaskReviewAction;
use App\Domains\Supervisor\Requests\AssignAssignmentTaskToStudentRequest;
use App\Domains\Supervisor\Requests\AssignProjectRequest;
use App\Domains\Supervisor\Requests\RequestAssignmentTaskRevisionRequest;
use App\Http\Resources\ProjectAssignmentResource;
use App\Http\Resources\ProjectAssignmentTaskResource;
use App\Models\ProjectAssignment;
use App\Models\ProjectAssignmentTask;
use App\Support\ApiResponse;
use App\Domains\Supervisor\Actions\GetActiveProjectAssignmentStudentsAction;

class ProjectAssignmentController extends Controller
{
    public function __construct() {}

    public function index()
    {
        $assignments = ProjectAssignment::query()
            ->with([
                'students',
                'supervisor',
                'projectTemplate',
                'assignmentTasks' => fn ($query) => $query->orderBy('order_index'),
            ])
            ->latest()
            ->paginate(10);

        return ApiResponse::success('Project assignments retrieved successfully',
            ProjectAssignmentResource::collection($assignments)
        );
    }

    public function assignProject(
        AssignProjectRequest $request,
        AssignProjectAction $assignProjectAction
    ) {
        $assignment = $assignProjectAction->execute(
            $request->validated()
        );

        return ApiResponse::success('Project assigned successfully',
            new ProjectAssignmentResource($assignment),
            201
        );
    }

    public function show(ProjectAssignment $projectAssignment)
    {
        $projectAssignment->load([
            'students',
            'supervisor',
            'projectTemplate',
            'evaluation',
            'assignmentTasks' => fn ($query) => $query->orderBy('order_index'),
        ]);

        return ApiResponse::success('Project assignment details retrieved successfully',
            new ProjectAssignmentResource($projectAssignment)
        );
    }

    public function activeStudents(
    ProjectAssignment $projectAssignment,
    GetActiveProjectAssignmentStudentsAction $action
    ) {
        $students = $action->execute(
            $projectAssignment,
            (int) auth()->id()
        );

        return ApiResponse::success(
            'Active project team students retrieved successfully',
            $students
        );
    }

    public function assignTaskToStudent(
        AssignAssignmentTaskToStudentRequest $request,
        ProjectAssignmentTask $projectAssignmentTask,
        AssignAssignmentTaskToStudentAction $action
    ) {
        $task = $action->execute(
            $projectAssignmentTask,
            $request->validated()['student_id']
        );

        return ApiResponse::success(
            'Task assigned to student successfully',
            new ProjectAssignmentTaskResource($task)
        );
    }

    public function startTask(
        ProjectAssignmentTask $projectAssignmentTask,
        StartAssignmentTaskAction $action
    ) {
        \Gate::authorize('start', $projectAssignmentTask);

        $task = $action->execute($projectAssignmentTask);

        return ApiResponse::success('Task started successfully',
            new ProjectAssignmentTaskResource($task),
        );
    }

    public function submitTask(
        SubmitAssignmentTaskRequest $request,
        ProjectAssignmentTask $projectAssignmentTask,
        SubmitAssignmentTaskAction $action
    ) {
        \Gate::authorize('submit', $projectAssignmentTask);

        $task = $action->execute(
            $projectAssignmentTask,
            $request->validated()
        );

        return ApiResponse::success('Task submitted successfully',
            new ProjectAssignmentTaskResource($task)
        );
    }

    public function startTaskReview(
        ProjectAssignmentTask $projectAssignmentTask,
        StartAssignmentTaskReviewAction $action
    ) {
        \Gate::authorize('review', $projectAssignmentTask);

        $task = $action->execute($projectAssignmentTask);

        return ApiResponse::success(
            'Task review started successfully',
            new ProjectAssignmentTaskResource($task)
        );
    }

    public function approveTask(
        ProjectAssignmentTask $projectAssignmentTask,
        ApproveAssignmentTaskAction $action,
        RecalculateProjectAssignmentProgressAction $recalculateProgress
    ) {
        \Gate::authorize('approve', $projectAssignmentTask);

        $task = $action->execute(
            $projectAssignmentTask,
            $recalculateProgress
        );

        return ApiResponse::success(
            'Task approved successfully',
            new ProjectAssignmentTaskResource($task)
        );
    }

    public function requestTaskRevision(
        RequestAssignmentTaskRevisionRequest $request,
        ProjectAssignmentTask $projectAssignmentTask,
        RequestAssignmentTaskRevisionAction $action
    ) {
        \Gate::authorize('requestRevision', $projectAssignmentTask);

        $task = $action->execute(
            $projectAssignmentTask,
            $request->validated()['feedback']
        );

        return ApiResponse::success(
            'Task revision requested successfully',
            new ProjectAssignmentTaskResource($task)
        );
    }
}
