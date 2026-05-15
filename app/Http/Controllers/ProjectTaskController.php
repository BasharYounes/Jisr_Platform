<?php

namespace App\Http\Controllers;

use App\Domains\Supervisor\Actions\CreateProjectTaskAction;
use App\Domains\Supervisor\Requests\CreateProjectTaskRequest;
use App\Models\ProjectTask;
use App\Models\ProjectTemplate;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class ProjectTaskController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(
        CreateProjectTaskRequest $request,
        ProjectTemplate $projectTemplate,
        CreateProjectTaskAction $action
    ) {
        $task = $action->execute(
            $projectTemplate,
            $request->validated()
        );

        return ApiResponse::success(
            'Project task created successfully',
            $task,
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(ProjectTask $projectTask)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ProjectTask $projectTask)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ProjectTask $projectTask)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ProjectTask $projectTask)
    {
        //
    }
}
