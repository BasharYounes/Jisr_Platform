<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompanyTasks\ReviewCompanyTaskApplicationRequest;
use App\Http\Resources\CompanyTasks\CompanyTaskApplicantCardResource;
use App\Http\Resources\CompanyTasks\CompanyTaskApplicantDetailsResource;
use App\Services\CompanyTasks\CompanyTaskApplicationService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class CompanyTaskApplicationController extends Controller
{
    use ApiResponse;
    public function __construct(
        private readonly CompanyTaskApplicationService $companyTaskApplicationService
    ) {}

    /**
     * Display applicants for a specific company task.
     */
    public function applications(int $taskId): AnonymousResourceCollection
    {
        $companyId = Auth::user()->companies()->firstOrFail()->id;
        $applications = $this->companyTaskApplicationService->getTaskApplications(
            companyId: $companyId,
            taskId: $taskId
        );

        return CompanyTaskApplicantCardResource::collection($applications);
    }

    public function show(int $applicationId): JsonResponse
{
    $companyId = Auth::user()->companies()->firstOrFail()->id;

    $application = $this->companyTaskApplicationService->getApplicationDetails(
        companyId: $companyId,
        applicationId: $applicationId
    );

    return response()->json([
         'data' => new CompanyTaskApplicantDetailsResource($application),
    ]);
}

    /**
     * Accept a student's application and create the official assignment.
     */
    public function accept(ReviewCompanyTaskApplicationRequest  $request, int $applicationId): JsonResponse {
        $companyId = $this->getAuthenticatedCompanyId();
        $assignment = $this->companyTaskApplicationService->acceptApplication(
            companyId: $companyId,
            applicationId: $applicationId,
            data: $request->validated()
        );

        return $this->success(
            message: 'تم قبول الطالب وبدء تنفيذ المهمة بنجاح. | Student accepted and task execution started successfully.',
            data: [
                'assignment_id' => $assignment->id,
                'application_id' => $assignment->company_task_application_id,
                'task_id' => $assignment->company_task_id,
                'student_user_id' => $assignment->student_user_id,
                'status' => $assignment->status,
                'started_at' => $assignment->started_at?->toISOString(),
            ]);
    }

    /**
     * Reject a student's application.
     */
    public function reject(
        ReviewCompanyTaskApplicationRequest $request,
        int $applicationId
    ): JsonResponse {
        $companyId = $this->getAuthenticatedCompanyId();

        $application = $this->companyTaskApplicationService->rejectApplication(
            companyId: $companyId,
            applicationId: $applicationId,
            data: $request->validated()
        );

         return $this->success(
        message: 'تم رفض طلب التقديم بنجاح. | Application rejected successfully.',
        data: [
            'application_id' => $application->id,
            'status' => $application->status,
            'company_notes' => $application->company_notes,
            'reviewed_at' => $application->reviewed_at?->toISOString(),
        ]
    );
    }

    /**
     * Get the company owned by the authenticated company user.
     */
    private function getAuthenticatedCompanyId(): int
    {
        return Auth::user()
            ->companies()
            ->firstOrFail()
            ->id;
    }
}