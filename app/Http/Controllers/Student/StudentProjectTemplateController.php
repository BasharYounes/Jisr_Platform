<?php

namespace App\Http\Controllers\Student;

use App\Domains\Student\Actions\ApplyToProjectTemplateAction;
use App\Domains\Student\Enums\ProjectTemplateApplicationStatus;
use App\Domains\Student\Requests\ApplyToProjectTemplateRequest;
use App\Http\Controllers\Controller;
use App\Http\Resources\Student\StudentProjectTemplateApplicationResource;
use App\Models\ProjectTemplate;
use App\Services\Student\StudentProjectTemplateApplicationService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StudentProjectTemplateController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly StudentProjectTemplateApplicationService $applicationService
    ) {}

    public function apply(
        ApplyToProjectTemplateRequest $request,
        ProjectTemplate $projectTemplate,
        ApplyToProjectTemplateAction $action
    ): JsonResponse {
        $application = $action->execute(
            projectTemplate: $projectTemplate,
            studentUserId: Auth::id(),
            data: $request->validated()
        );

        return response()->json([
            'message' => 'تم إرسال طلب التقديم بنجاح. | Application submitted successfully.',
            'data' => [
                'application_id' => $application->id,
                'project_template_id' => $application->project_template_id,
                'status' => $application->status->value,
                'applied_at' => $application->applied_at,
            ],
        ], 201);
    }

    public function all(Request $request): JsonResponse
    {
        $applications = $this->applicationService->getAllApplications(
            $request->user()->id
        );

        return $this->success(
            'تم جلب جميع طلبات المشاريع بنجاح. | Project applications retrieved successfully.',
            [
                'pending' => StudentProjectTemplateApplicationResource::collection($applications['pending']),
                'accepted' => StudentProjectTemplateApplicationResource::collection($applications['accepted']),
                'rejected' => StudentProjectTemplateApplicationResource::collection($applications['rejected']),
            ]
        );
    }

    public function pending(Request $request): JsonResponse
    {
        $applications = $this->applicationService->getApplicationsByStatus(
            $request->user()->id,
            ProjectTemplateApplicationStatus::PENDING
        );

        return $this->success(
            'تم جلب طلبات المشاريع قيد المراجعة بنجاح. | Pending project applications retrieved successfully.',
            StudentProjectTemplateApplicationResource::collection($applications)
        );
    }

    public function accepted(Request $request): JsonResponse
    {
        $applications = $this->applicationService->getApplicationsByStatus(
            $request->user()->id,
            ProjectTemplateApplicationStatus::ACCEPTED
        );

        return $this->success(
            'تم جلب طلبات المشاريع المقبولة بنجاح. | Accepted project applications retrieved successfully.',
            StudentProjectTemplateApplicationResource::collection($applications)
        );
    }

    public function rejected(Request $request): JsonResponse
    {
        $applications = $this->applicationService->getApplicationsByStatus(
            $request->user()->id,
            ProjectTemplateApplicationStatus::REJECTED
        );

        return $this->success(
            'تم جلب طلبات المشاريع المرفوضة بنجاح. | Rejected project applications retrieved successfully.',
            StudentProjectTemplateApplicationResource::collection($applications)
        );
    }
}
