<?php

namespace App\Http\Controllers\StudentOpportunity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Opportunities\IndexStudentOpportunityInterviewRequest;
use App\Http\Resources\Opportunities\StudentOpportunityInterviewResource;
use App\Services\Opportunities\StudentOpportunityInterviewService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class StudentOpportunityInterviewController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly StudentOpportunityInterviewService $interviewService
    ) {}

    public function index(
        IndexStudentOpportunityInterviewRequest $request
    ): JsonResponse {
        $interviews = $this->interviewService->getInterviews(
            studentUserId: (int) $request->user()->id,
            filters: $request->validated()
        );

        return $this->success(
            message: 'تم جلب مقابلات الطالب بنجاح. | Student interviews retrieved successfully.',
            data: StudentOpportunityInterviewResource::collection(
                $interviews
            )
        );
    }
}
