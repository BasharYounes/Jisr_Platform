<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompanyTasks\StoreCompanyTaskRequest;
use App\Http\Requests\CompanyTasks\UpdateCompanyTaskRequest;
use App\Http\Resources\CompanyTasks\CompanyTaskAssignmentDetailsResource;
use App\Http\Resources\CompanyTasks\CompanyTaskResource;
use App\Services\CompanyTasks\CompanyTaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class CompanyTaskController extends Controller
{
    public function __construct(
        private readonly CompanyTaskService $companyTaskService
    ) {}

   public function index(Request $request): JsonResponse
{
    $companyId = Auth::user()->companies()->firstOrFail()->id;

    $data = $request->validate([
        'status' => [
            'nullable',
            'string',
            Rule::in([
                'draft',
                'published',
                'in_progress',
                'closed',
                'cancelled',
            ]),
        ],
    ]);

    $tasks = $this->companyTaskService->getCompanyTasks(
        companyId: $companyId,
        status: $data['status'] ?? null
    );

    return response()->json([
        'data' => CompanyTaskResource::collection($tasks),
    ]);
}

    public function store(StoreCompanyTaskRequest $request): JsonResponse
    {
        $companyId = Auth::user()->companies()->firstOrFail()->id;

        $task = $this->companyTaskService->createTask(
            companyId: $companyId,
            data: $request->validated()
        );

        return response()->json([
            'message' => 'تم إنشاء المهمة بنجاح. | Task created successfully.',
            'data' => new CompanyTaskResource($task),
        ], 201);
    }

    public function show(int $taskId): JsonResponse
    {
        $companyId = Auth::user()->companies()->firstOrFail()->id;

        $task = $this->companyTaskService->getCompanyTaskDetails(
            companyId: $companyId,
            taskId: $taskId
        );

        return response()->json([
            'data' => new CompanyTaskResource($task),
        ]);
    }

    public function update(UpdateCompanyTaskRequest $request, int $taskId): JsonResponse
    {
        $companyId = Auth::user()->companies()->firstOrFail()->id;

        $task = $this->companyTaskService->updateTask(
            companyId: $companyId,
            taskId: $taskId,
            data: $request->validated()
        );

        return response()->json([
            'message' => 'تم تعديل المهمة بنجاح. | Task updated successfully.',
            'data' => new CompanyTaskResource($task),
        ]);
    }

    public function publish(int $taskId): JsonResponse
    {
        $companyId = Auth::user()->companies()->firstOrFail()->id;

        $task = $this->companyTaskService->publishTask(
            companyId: $companyId,
            taskId: $taskId
        );

        return response()->json([
            'message' => 'تم نشر المهمة بنجاح. | Task published successfully.',
            'data' => new CompanyTaskResource($task),
        ]);
    }

    public function close(int $taskId): JsonResponse
    {
        $companyId = Auth::user()->companies()->firstOrFail()->id;

        $blockingAssignments = $this->companyTaskService
            ->getTaskCloseBlockingAssignments(
                companyId: $companyId,
                taskId: $taskId
            );

        if ($blockingAssignments->isNotEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'لا يمكن إغلاق التاسك قبل تقييم كل الطلاب المرتبطين به. | Cannot close the task before reviewing all assigned students.',
                'data' => [
                    'unreviewed_assignments' => CompanyTaskAssignmentDetailsResource::collection(
                        $blockingAssignments
                    ),
                ],
            ], 422);
        }

        $task = $this->companyTaskService->closeTask(
            companyId: $companyId,
            taskId: $taskId
        );

        return response()->json([
            'status' => true,
            'message' => 'تم إغلاق التاسك بنجاح. | Task closed successfully.',
            'data' => new CompanyTaskResource($task),
        ]);
    }

    public function cancel(Request $request, int $taskId): JsonResponse
{
    $companyId = Auth::user()->companies()->firstOrFail()->id;

    $data = $request->validate([
        'reason' => [
            'nullable',
            'string',
            'max:1000',
        ],
    ]);

    $blockingAssignments = $this->companyTaskService
        ->getTaskCancellationBlockingAssignments(
            companyId: $companyId,
            taskId: $taskId
        );

    if ($blockingAssignments->isNotEmpty()) {
        return response()->json([
            'status' => false,
            'message' => 'لا يمكن إلغاء التاسك لوجود طلاب تم قبولهم عليه. | Cannot cancel the task because there are accepted students assigned to it.',
            'data' => [
                'blocking_assignments' => CompanyTaskAssignmentDetailsResource::collection(
                    $blockingAssignments
                ),
            ],
        ], 422);
    }

    $task = $this->companyTaskService->cancelTask(
        companyId: $companyId,
        taskId: $taskId,
        reason: $data['reason'] ?? null
    );

    return response()->json([
        'status' => true,
        'message' => 'تم إلغاء التاسك بنجاح. | Task cancelled successfully.',
        'data' => new CompanyTaskResource($task),
    ]);
}
}