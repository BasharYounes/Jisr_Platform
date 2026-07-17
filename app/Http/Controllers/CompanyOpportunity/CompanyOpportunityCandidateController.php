<?php

namespace App\Http\Controllers\CompanyOpportunity;

use App\Http\Controllers\Controller;
use App\Http\Resources\Opportunities\CompanyCandidateResource;
use App\Services\Opportunities\CompanyOpportunityCandidateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyOpportunityCandidateController extends Controller
{
    public function __construct(
        private readonly CompanyOpportunityCandidateService $candidateService
    ) {}

    public function index(
        Request $request,
        int $opportunityId
    ): JsonResponse {
        $candidates = $this->candidateService->getCandidates(
            companyId: $this->getAuthenticatedCompanyId($request),
            opportunityId: $opportunityId
        );

        return response()->json([
            'success' => true,
            'message' => 'تم جلب المرشحين بنجاح. | Candidates retrieved successfully.',
            'data' => CompanyCandidateResource::collection($candidates),
        ]);
    }

    public function show(
        Request $request,
        int $opportunityId,
        int $applicationId
    ): JsonResponse {
        $candidate = $this->candidateService->getCandidateDetails(
            companyId: $this->getAuthenticatedCompanyId($request),
            opportunityId: $opportunityId,
            applicationId: $applicationId
        );

        return response()->json([
            'success' => true,
            'message' => 'تم جلب تفاصيل المرشح بنجاح. | Candidate details retrieved successfully.',
            'data' => new CompanyCandidateResource($candidate),
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
