<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompanyTasks\ApplyToCompanyTaskRequest;
use App\Http\Resources\CompanyTasks\StudentTaskCardResource;
use App\Http\Resources\CompanyTasks\StudentTaskResource;
use App\Services\CompanyTasks\StudentTaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class StudentTaskController extends Controller
{
    public function __construct(
        private readonly StudentTaskService $studentTaskService
    ) {}

    public function explore(Request $request): AnonymousResourceCollection
    {
        $tasks = $this->studentTaskService->getExploreTasks(
            title: $request->query('title')
        );

        return StudentTaskCardResource::collection($tasks);
    }

    public function recommended(): AnonymousResourceCollection
    {
        $userId = Auth::id();
        $tasks = $this->studentTaskService->getRecommendedTasks(
            studentUserId: $userId
        );

        return StudentTaskCardResource::collection($tasks);
    }

    public function show(int $taskId): JsonResponse
    {
        $task = $this->studentTaskService->getTaskDetails($taskId);

        return response()->json([
            'data' => new StudentTaskResource($task),
        ]);
    }

    public function apply(
        ApplyToCompanyTaskRequest $request,
        int $taskId
    ): JsonResponse {
        $application = $this->studentTaskService->applyToTask(
            studentUserId: Auth::id(),
            taskId: $taskId,
            data: $request->validated()
        );

        return response()->json([
            'message' => 'تم إرسال طلب التقديم بنجاح. | Application submitted successfully.',
            'data' => [
                'application_id' => $application->id,
                'status' => $application->status,
            ],
        ], 201);
    }
}
