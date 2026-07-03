<?php

namespace App\Http\Controllers\StudentOpportunity;

use App\Http\Controllers\Controller;
use App\Http\Resources\Opportunities\OpportunityApplicationResource;
use App\Services\Opportunities\StudentOpportunityApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentApplicationController extends Controller
{
    public function __construct(
        private readonly StudentOpportunityApplicationService $applicationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $applications = $this->applicationService
            ->getStudentApplications((int) $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'تم جلب طلبات التقديم بنجاح. | Applications retrieved successfully.',
            'data' => OpportunityApplicationResource::collection($applications),
        ]);
    }

    public function show(
        Request $request,
        int $applicationId
    ): JsonResponse {
        $application = $this->applicationService
            ->getStudentApplicationDetails(
                studentUserId: (int) $request->user()->id,
                applicationId: $applicationId
            );

        return response()->json([
            'success' => true,
            'message' => 'تم جلب تفاصيل طلب التقديم بنجاح. | Application details retrieved successfully.',
            'data' => new OpportunityApplicationResource($application),
        ]);
    }

    public function withdraw(
        Request $request,
        int $applicationId
    ): JsonResponse {
        $application = $this->applicationService
            ->withdraw(
                studentUserId: (int) $request->user()->id,
                applicationId: $applicationId
            );

        return response()->json([
            'success' => true,
            'message' => 'تم سحب طلب التقديم بنجاح. | Application withdrawn successfully.',
            'data' => new OpportunityApplicationResource($application),
        ]);
    }
}
