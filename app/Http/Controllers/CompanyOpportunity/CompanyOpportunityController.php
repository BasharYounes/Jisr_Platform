<?php

namespace App\Http\Controllers\CompanyOpportunity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Opportunities\IndexCompanyOpportunityRequest;
use App\Http\Requests\Opportunities\StoreCompanyOpportunityRequest;
use App\Http\Requests\Opportunities\UpdateCompanyOpportunityRequest;
use App\Http\Resources\Opportunities\OpportunityResource;
use App\Services\Opportunities\CompanyOpportunityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyOpportunityController extends Controller
{
    public function __construct(
        private readonly CompanyOpportunityService $companyOpportunityService
    ) {}

    public function index(IndexCompanyOpportunityRequest $request): JsonResponse
    {
        $companyId = $this->getAuthenticatedCompanyId($request);

        $data = $request->validated();

        $opportunities = $this->companyOpportunityService
            ->getCompanyOpportunities(
                companyId: $companyId,
                status: $data['status'] ?? null,
                type: $data['type'] ?? null,
                search: $data['search'] ?? null
            );

        return response()->json([
            'status' => true,
            'message' => 'تم جلب فرص الشركة بنجاح. | Company opportunities retrieved successfully.',
            'data' => OpportunityResource::collection($opportunities),
        ]);
    }

    public function store(StoreCompanyOpportunityRequest $request): JsonResponse
    {
        $companyId = $this->getAuthenticatedCompanyId($request);

        $opportunity = $this->companyOpportunityService
            ->createOpportunity(
                companyId: $companyId,
                data: $request->validated()
            );

        return response()->json([
            'status' => true,
            'message' => 'تم إنشاء الفرصة كمسودة بنجاح. | Opportunity draft created successfully.',
            'data' => new OpportunityResource($opportunity),
        ], 201);
    }

    public function show(Request $request, int $opportunityId): JsonResponse
    {
        $companyId = $this->getAuthenticatedCompanyId($request);

        $opportunity = $this->companyOpportunityService
            ->getCompanyOpportunityDetails(
                companyId: $companyId,
                opportunityId: $opportunityId
            );

        return response()->json([
            'status' => true,
            'message' => 'تم جلب تفاصيل الفرصة بنجاح. | Opportunity details retrieved successfully.',
            'data' => new OpportunityResource($opportunity),
        ]);
    }

    public function update(
        UpdateCompanyOpportunityRequest $request,
        int $opportunityId
    ): JsonResponse {
        $companyId = $this->getAuthenticatedCompanyId($request);

        $opportunity = $this->companyOpportunityService
            ->updateOpportunity(
                companyId: $companyId,
                opportunityId: $opportunityId,
                data: $request->validated()
            );

        return response()->json([
            'status' => true,
            'message' => 'تم تعديل الفرصة بنجاح. | Opportunity updated successfully.',
            'data' => new OpportunityResource($opportunity),
        ]);
    }

    public function publish(Request $request, int $opportunityId): JsonResponse
    {
        $companyId = $this->getAuthenticatedCompanyId($request);

        $opportunity = $this->companyOpportunityService
            ->publishOpportunity(
                companyId: $companyId,
                opportunityId: $opportunityId
            );

        return response()->json([
            'status' => true,
            'message' => 'تم نشر الفرصة بنجاح. | Opportunity published successfully.',
            'data' => new OpportunityResource($opportunity),
        ]);
    }

    public function close(Request $request, int $opportunityId): JsonResponse
    {
        $companyId = $this->getAuthenticatedCompanyId($request);

        $opportunity = $this->companyOpportunityService
            ->closeOpportunity(
                companyId: $companyId,
                opportunityId: $opportunityId
            );

        return response()->json([
            'status' => true,
            'message' => 'تم إغلاق الفرصة بنجاح. | Opportunity closed successfully.',
            'data' => new OpportunityResource($opportunity),
        ]);
    }

    public function cancel(Request $request, int $opportunityId): JsonResponse
    {
        $companyId = $this->getAuthenticatedCompanyId($request);

        $opportunity = $this->companyOpportunityService
            ->cancelOpportunity(
                companyId: $companyId,
                opportunityId: $opportunityId
            );

        return response()->json([
            'status' => true,
            'message' => 'تم إلغاء الفرصة بنجاح. | Opportunity cancelled successfully.',
            'data' => new OpportunityResource($opportunity),
        ]);
    }

    public function destroy(Request $request, int $opportunityId): JsonResponse
    {
        $companyId = $this->getAuthenticatedCompanyId($request);

        $this->companyOpportunityService
            ->deleteOpportunity(
                companyId: $companyId,
                opportunityId: $opportunityId
            );

        return response()->json([
            'status' => true,
            'message' => 'تم حذف الفرصة بنجاح. | Opportunity deleted successfully.',
            'data' => null,
        ]);
    }

    private function getAuthenticatedCompanyId(Request $request): int
    {
        return (int) $request->user()
            ->companies()
            ->firstOrFail()
            ->id;
    }
}
