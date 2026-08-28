<?php

namespace App\Http\Controllers\Student;

use App\Domains\Student\Actions\ApplyToProjectTemplateAction;
use App\Domains\Student\Actions\GetStudentProjectTemplateDetailsAction;
use App\Domains\Student\Actions\ListStudentProjectTemplatesAction;
use App\Domains\Student\Enums\ProjectTemplateApplicationStatus;
use App\Domains\Student\Requests\ApplyToProjectTemplateRequest;
use App\Domains\Student\Requests\ListStudentProjectTemplatesRequest;
use App\Http\Controllers\Controller;
use App\Http\Resources\Student\StudentProjectTemplateApplicationResource;
use App\Http\Resources\Student\StudentProjectTemplateResource;
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

    public function index(
        ListStudentProjectTemplatesRequest $request,
        ListStudentProjectTemplatesAction $action
    ): JsonResponse {
        $projects = $action->execute(
            student: $request->user(),
            filters: $request->validated()
        );

        return $this->success(
            'تم جلب مشاريع المشرفين المتاحة للطالب بنجاح. | Supervisor project templates retrieved successfully.',
            [
                'projects' => StudentProjectTemplateResource::collection(
                    $projects->getCollection()
                )->resolve($request),
                'pagination' => [
                    'current_page' => $projects->currentPage(),
                    'last_page' => $projects->lastPage(),
                    'per_page' => $projects->perPage(),
                    'total' => $projects->total(),
                ],
            ]
        );
    }

    public function show(
        Request $request,
        ProjectTemplate $projectTemplate,
        GetStudentProjectTemplateDetailsAction $action
    ): JsonResponse {
        $project = $action->execute(
            projectTemplate: $projectTemplate,
            student: $request->user()
        );

        return $this->success(
            'تم جلب تفاصيل المشروع بنجاح. | Supervisor project template retrieved successfully.',
            new StudentProjectTemplateResource($project)
        );
    }

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
