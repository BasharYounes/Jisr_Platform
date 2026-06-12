<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyTasks\CompanyTaskSubmissionResource;
use App\Services\CompanyTasks\CompanyTaskSubmissionService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyTaskSubmissionController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly CompanyTaskSubmissionService $submissionService
    ) {}

    public function show(
        Request $request,
        int $assignmentId
    ): JsonResponse {
        $companyId = $this->submissionService->getAuthenticatedCompanyId($request);

        $submission = $this->submissionService->getCompanySubmission(
            $assignmentId,
            $companyId
        );

        return $this->success(
            message: 'تم جلب التسليم النهائي للطالب بنجاح. | Student final submission retrieved successfully.',
            data: new CompanyTaskSubmissionResource($submission)
        );
    }

   
}