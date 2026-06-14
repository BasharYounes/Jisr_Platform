<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompanyTasks\StoreCompanyTaskReviewRequest;
use App\Http\Resources\CompanyTasks\CompanyTaskReviewResource;
use App\Services\CompanyTasks\CompanyTaskReviewService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyTaskReviewController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly CompanyTaskReviewService $reviewService
    ) {}

    public function store(
        StoreCompanyTaskReviewRequest $request,
        int $submissionId
    ): JsonResponse {
        $review = $this->reviewService->createReview(
            submissionId: $submissionId,
            companyId: $this->getAuthenticatedCompanyId($request),
            data: $request->validated()
        );

        return $this->success(
            message: 'تمت مراجعة التسليم النهائي بنجاح. | Final submission reviewed successfully.',
            data: new CompanyTaskReviewResource($review),
            statusCode: 201
        );
    }

    public function show(
        Request $request,
        int $submissionId
    ): JsonResponse {
        $review = $this->reviewService->getCompanyReview(
            submissionId: $submissionId,
            companyId: $this->getAuthenticatedCompanyId($request)
        );

        return $this->success(
            message: 'تم جلب مراجعة التسليم بنجاح. | Submission review retrieved successfully.',
            data: new CompanyTaskReviewResource($review)
        );
    }

    private function getAuthenticatedCompanyId(
        Request $request
    ): int {
        return (int) $request->user()
            ->companies()
            ->firstOrFail()
            ->id;
    }
}
