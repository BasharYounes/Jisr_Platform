<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyTasks\CompanyTaskAssignmentCardResource;
use App\Http\Resources\CompanyTasks\CompanyTaskAssignmentDetailsResource;
use App\Services\CompanyTasks\CompanyTaskAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class CompanyTaskAssignmentController extends Controller
{
    public function __construct(
        private readonly CompanyTaskAssignmentService $assignmentService
    ) {}

    public function index(): JsonResponse
    {
        $companyId = Auth::user()->companies()->firstOrFail()->id;

        $assignments = $this->assignmentService->getCompanyAssignments($companyId);

        return response()->json([
            'data' => CompanyTaskAssignmentCardResource::collection($assignments),
        ]);
    }

    public function show(int $assignmentId): JsonResponse
    {
        $companyId = Auth::user()->companies()->firstOrFail()->id;

        $assignment = $this->assignmentService->getCompanyAssignmentDetails(
            companyId: $companyId,
            assignmentId: $assignmentId
        );

        return response()->json([
            'data' => new CompanyTaskAssignmentDetailsResource($assignment),
        ]);
    }

    public function close(int $assignmentId): JsonResponse
{
    $companyId = Auth::user()->companies()->firstOrFail()->id;

    $assignment = $this->assignmentService->closeCompanyAssignment(
        companyId: $companyId,
        assignmentId: $assignmentId
    );

    return response()->json([
        'status' => true,
        'message' => 'تم إغلاق تكليف الطالب بنجاح. | Assignment closed successfully.',
        'data' => new CompanyTaskAssignmentDetailsResource($assignment),
    ]);
}
}