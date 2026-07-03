<?php

namespace App\Http\Controllers;

use App\Domains\Supervisor\Actions\CreateProjectTemplateAction;
use App\Domains\Supervisor\Requests\CreateProjectTemplateRequest;
use App\Http\Resources\ProjectTemplateResource;
use App\Models\ProjectTemplate;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use App\Domains\Supervisor\Actions\UpdateProjectTemplateAction;
use App\Domains\Supervisor\Requests\UpdateProjectTemplateRequest;

class ProjectTemplateController extends Controller
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
    public function create(
        CreateProjectTemplateRequest $request,
        CreateProjectTemplateAction $createProjectTemplateAction
    ) {
        $template = $createProjectTemplateAction->execute(
            $request->validated()
        );

        return ApiResponse::success('Project template created successfully',
            new ProjectTemplateResource($template),
            201
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(ProjectTemplate $projectTemplate)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ProjectTemplate $projectTemplate)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(
    UpdateProjectTemplateRequest $request,
    ProjectTemplate $projectTemplate,
    UpdateProjectTemplateAction $action
    ) {
        $template = $action->execute(
            $projectTemplate,
            (int) auth()->id(),
            $request->validated()
        );

        return ApiResponse::success(
            'Project template updated successfully',
            new ProjectTemplateResource($template)
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ProjectTemplate $projectTemplate)
    {
        //
    }
}
