<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyTasks\CompanyTaskProgressResource;
use App\Services\CompanyTasks\CompanyTaskProgressService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyTaskProgressController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly CompanyTaskProgressService $progressService
    ) {}

    public function index(Request $request, int $assignmentId): JsonResponse
    {
        $companyId = $this->getAuthenticatedCompanyId($request);

        $result = $this->progressService->getCompanyProgressUpdates(
            $assignmentId,
            $companyId
        );

        return $this->success(
            message: 'تم جلب تحديثات تقدم الطالب بنجاح. | Task progress timeline retrieved successfully.',
            data: [
                'assignment' => [
                    'id' => $result['assignment']->id,
                    'status' => $result['assignment']->status,
                    'started_at' => $result['assignment']->started_at,
                    'submitted_at' => $result['assignment']->submitted_at,

                    'student' => [
                        'id' => $result['assignment']->student?->id,
                        'name' => $result['assignment']->student?->name,
                        'email' => $result['assignment']->student?->email,
                    ],

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

    private function getAuthenticatedCompanyId(Request $request): int
    {
        return (int) $request->user()
            ->companies()
            ->firstOrFail()
            ->id;
    }
}
