<?php

namespace App\Http\Controllers\StudentOpportunity;

use App\Http\Controllers\Controller;
use App\Http\Resources\Opportunities\StudentOpportunityDetailsResource;
use App\Http\Resources\Opportunities\StudentOpportunityResource;
use App\Services\Opportunities\StudentOpportunityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentOpportunityController extends Controller
{
    public function __construct(
        private readonly StudentOpportunityService $studentOpportunityService
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
}
