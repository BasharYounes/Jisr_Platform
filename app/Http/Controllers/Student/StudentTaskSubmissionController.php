<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompanyTasks\StoreCompanyTaskSubmissionRequest;
use App\Http\Resources\CompanyTasks\CompanyTaskSubmissionResource;
use App\Services\CompanyTasks\CompanyTaskSubmissionService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentTaskSubmissionController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly CompanyTaskSubmissionService $submissionService
    ) {}

    public function store(
        StoreCompanyTaskSubmissionRequest $request,
        int $assignmentId
    ): JsonResponse {
        $submission = $this->submissionService->submit(
            $assignmentId,
            $request->user()->id,
            $request->validated()
        );

        return $this->success(
            message: 'تم إرسال التسليم النهائي بنجاح. | Final submission sent successfully.',
            // data: new CompanyTaskSubmissionResource($submission),
            statusCode: 201
        );
    }

    public function show(
        Request $request,
        int $assignmentId
    ): JsonResponse {
        $submission = $this->submissionService->getStudentSubmission(
            $assignmentId,
            $request->user()->id
        );

        return $this->success(
            message: 'تم جلب التسليم النهائي بنجاح. | Final submission retrieved successfully.',
            data: new CompanyTaskSubmissionResource($submission)
        );
    }
}
