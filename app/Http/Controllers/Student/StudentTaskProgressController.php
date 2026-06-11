<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompanyTasks\StoreCompanyTaskProgressRequest;
use App\Http\Resources\CompanyTasks\CompanyTaskProgressResource;
use App\Services\CompanyTasks\CompanyTaskProgressService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentTaskProgressController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly CompanyTaskProgressService $progressService
    ) {}

    public function index(
        Request $request,
        int $assignmentId
    ): JsonResponse {
        $result = $this->progressService->getStudentProgressUpdates(
            $assignmentId,
            $request->user()->id
        );

        return $this->success(
            message: 'تم جلب تحديثات تقدم المهمة بنجاح. | Task progress updates retrieved successfully.',
            data: [
                'assignment' => [
                    'id' => $result['assignment']->id,
                    'status' => $result['assignment']->status,
                    'started_at' => $result['assignment']->started_at,
                    'task' => [
                        'id' => $result['assignment']->task?->id,
                        'title' => $result['assignment']->task?->title,
                        'deadline' => $result['assignment']->task?->deadline,
                    ],
                ],

                'progress_updates' => CompanyTaskProgressResource::collection(
                    $result['updates']
                ),
            ]
        );
    }

    public function store(
        StoreCompanyTaskProgressRequest $request,
        int $assignmentId
    ): JsonResponse {
        $progressUpdate = $this->progressService->createProgressUpdate(
            $assignmentId,
            $request->user()->id,
            $request->validated()
        );

        $progressUpdate->load('student:id,name,email');

        return $this->success(
            message: 'تم إضافة تحديث التقدم بنجاح. | Progress update created successfully.',
            data: new CompanyTaskProgressResource($progressUpdate),
            statusCode: 201
        );
    }
}
