<?php

namespace App\Http\Controllers\StudentOpportunity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Opportunities\ApplyToOpportunityRequest;
use App\Http\Resources\Opportunities\OpportunityApplicationResource;
use App\Http\Resources\Opportunities\StudentOpportunityDetailsResource;
use App\Http\Resources\Opportunities\StudentOpportunityResource;
use App\Services\Opportunities\StudentOpportunityApplicationService;
use App\Services\Opportunities\StudentOpportunityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentOpportunityController extends Controller
{
    public function __construct(
        private readonly StudentOpportunityService $studentOpportunityService,
        private readonly StudentOpportunityApplicationService $applicationService
    ) {}

    public function recommended(Request $request): JsonResponse
    {
        $opportunities = $this->studentOpportunityService
            ->getRecommended((int) $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'تم جلب الفرص الموصى بها بنجاح. | Recommended opportunities retrieved successfully.',
            'data' => StudentOpportunityResource::collection($opportunities),
        ]);
    }

    public function explore(Request $request): JsonResponse
    {
        $opportunities = $this->studentOpportunityService
            ->getExplore((int) $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'تم جلب فرص الاستكشاف بنجاح. | Explore opportunities retrieved successfully.',
            'data' => StudentOpportunityResource::collection($opportunities),
        ]);
    }

    public function show(
        Request $request,
        int $opportunityId
    ): JsonResponse {
        $opportunity = $this->studentOpportunityService
            ->show(
                studentUserId: (int) $request->user()->id,
                opportunityId: $opportunityId
            );

        return response()->json([
            'success' => true,
            'message' => 'تم جلب تفاصيل الفرصة بنجاح. | Opportunity details retrieved successfully.',
            'data' => new StudentOpportunityDetailsResource($opportunity),
        ]);
    }

    public function apply(
        ApplyToOpportunityRequest $request,
        int $opportunityId
    ): JsonResponse {
        $application = $this->applicationService->apply(
            studentUserId: (int) $request->user()->id,
            opportunityId: $opportunityId,
            data: $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'تم إرسال طلب التقديم بنجاح. | Application submitted successfully.',
            'data' => new OpportunityApplicationResource($application),
        ], 201);
    }
}
