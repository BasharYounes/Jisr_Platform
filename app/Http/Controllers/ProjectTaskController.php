<?php

namespace App\Http\Controllers;

use App\Domains\Supervisor\Actions\CreateProjectTaskAction;
use App\Domains\Supervisor\Actions\SyncProjectTaskToAssignmentsAction;
use App\Domains\Supervisor\Requests\CreateProjectTaskRequest;
use App\Domains\Supervisor\Support\ProjectTemplateAuthorization;
use App\Models\ProjectTask;
use App\Models\ProjectTemplate;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class ProjectTaskController extends Controller
{
    public function index()
    {
        //
    }

    public function create()
    {
        //
    }

    public function store(
        CreateProjectTaskRequest $request,
        ProjectTemplate $projectTemplate,
        CreateProjectTaskAction $action,
        SyncProjectTaskToAssignmentsAction $syncProjectTaskToAssignmentsAction
    ) {
        ProjectTemplateAuthorization::ensureCreator(
            $projectTemplate,
            (int) $request->user()->id
        );

        $task = $action->execute(
            $projectTemplate,
            $request->validated()
        );

        $syncProjectTaskToAssignmentsAction->execute($task);

        return ApiResponse::success(
            'Project task created successfully',
            $task,
            201
        );
    }

    public function show(ProjectTask $projectTask)
    {
        //
    }

    public function edit(ProjectTask $projectTask)
    {
        //
    }

    public function update(Request $request, ProjectTask $projectTask)
    {
        //
    }

    public function destroy(ProjectTask $projectTask)
    {
        //
    }
}
