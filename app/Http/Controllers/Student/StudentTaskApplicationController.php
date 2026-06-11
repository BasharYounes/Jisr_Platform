<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Resources\Student\StudentTaskApplicationResource;
use App\Http\Resources\Student\StudentTaskAssignmentResource;
use App\Services\Student\StudentTaskApplicationService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentTaskApplicationController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly StudentTaskApplicationService $studentTaskApplicationService
    ) {}

    public function all(Request $request): JsonResponse
    {
        $tasks = $this->studentTaskApplicationService->getAllStudentTaskApplications(
            $request->user()->id
        );

        return $this->success(
            'تم جلب جميع حالات مهام الطالب بنجاح. | Student task applications retrieved successfully.',
            [
                'applied' => StudentTaskApplicationResource::collection($tasks['applied']),
                'accepted' => StudentTaskAssignmentResource::collection($tasks['accepted']),
                'rejected' => StudentTaskApplicationResource::collection($tasks['rejected']),
            ]
        );
    }

    public function applied(Request $request): JsonResponse
    {
        $applications = $this->studentTaskApplicationService->getAppliedTasks(
            $request->user()->id
        );

        return $this->success(
            'تم جلب المهام المقدّم عليها بنجاح. | Applied tasks retrieved successfully.',
            StudentTaskApplicationResource::collection($applications)
        );
    }

    public function accepted(Request $request): JsonResponse
    {
        $assignments = $this->studentTaskApplicationService->getAcceptedTasks(
            $request->user()->id
        );

        return $this->success(
            'تم جلب المهام المقبول بها بنجاح. | Accepted tasks retrieved successfully.',
            StudentTaskAssignmentResource::collection($assignments)
        );
    }

    public function rejected(Request $request): JsonResponse
    {
        $applications = $this->studentTaskApplicationService->getRejectedTasks(
            $request->user()->id
        );

        return $this->success(
            'تم جلب المهام المرفوضة بنجاح. | Rejected tasks retrieved successfully.',
            StudentTaskApplicationResource::collection($applications)
        );
    }
}
