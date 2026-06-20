<?php

namespace App\Http\Controllers\Supervisor;

use App\Domains\Supervisor\Actions\AcceptProjectTemplateApplicationAction;
use App\Domains\Supervisor\Actions\RejectProjectTemplateApplicationAction;
use App\Domains\Supervisor\Requests\ReviewProjectTemplateApplicationRequest;
use App\Http\Controllers\Controller;
use App\Http\Resources\Supervisor\ProjectTemplateApplicantDetailsResource;
use App\Http\Resources\Supervisor\ProjectTemplateApplicantResource;
use App\Models\ProjectTemplate;
use App\Models\ProjectTemplateApplication;
use App\Services\Supervisor\SupervisorProjectTemplateApplicationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ProjectTemplateApplicationController extends Controller
{
    public function __construct(
        private readonly SupervisorProjectTemplateApplicationService $applicationService
    ) {}

    public function index(ProjectTemplate $projectTemplate)
    {
        $applications = $this->applicationService->getTemplateApplications(
            projectTemplate: $projectTemplate,
            supervisorId: Auth::id()
        );

        return ApiResponse::success(
            'Project template applications retrieved successfully',
            ProjectTemplateApplicantResource::collection($applications)
        );
    }

    public function show(ProjectTemplateApplication $projectTemplateApplication)
    {
        $application = $this->applicationService->getApplicationDetails(
            application: $projectTemplateApplication,
            supervisorId: Auth::id()
        );

        return ApiResponse::success(
            'Application details retrieved successfully',
            new ProjectTemplateApplicantDetailsResource($application)
        );
    }

    public function accept(
        ReviewProjectTemplateApplicationRequest $request,
        ProjectTemplateApplication $projectTemplateApplication,
        AcceptProjectTemplateApplicationAction $action
    ): JsonResponse {
        $application = $action->execute(
            application: $projectTemplateApplication,
            supervisorId: Auth::id(),
            data: $request->validated()
        );

        return ApiResponse::success(
            'تم قبول الطالب وإنشاء تكليف المشروع بنجاح. | Student accepted and project assignment created successfully.',
            new ProjectTemplateApplicantDetailsResource($application),
            200
        );
    }

    public function reject(
        ReviewProjectTemplateApplicationRequest $request,
        ProjectTemplateApplication $projectTemplateApplication,
        RejectProjectTemplateApplicationAction $action
    ): JsonResponse {
        $application = $action->execute(
            application: $projectTemplateApplication,
            supervisorId: Auth::id(),
            data: $request->validated()
        );

        return ApiResponse::success(
            'تم رفض طلب التقديم بنجاح. | Application rejected successfully.',
            new ProjectTemplateApplicantDetailsResource($application)
        );
    }
}
