<?php

namespace App\Http\Controllers\CompanyOpportunity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Opportunities\CompleteOpportunityInterviewRequest;
use App\Http\Requests\Opportunities\RescheduleOpportunityInterviewRequest;
use App\Http\Requests\Opportunities\ScheduleOpportunityInterviewRequest;
use App\Http\Resources\Opportunities\OpportunityInterviewResource;
use App\Services\Opportunities\OpportunityInterviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OpportunityInterviewController extends Controller
{
    public function __construct(
        private readonly OpportunityInterviewService $interviewService
    ) {}

    public function schedule(
        ScheduleOpportunityInterviewRequest $request,
        int $opportunityId,
        int $applicationId
    ): JsonResponse {
        $interview = $this->interviewService->schedule(
            companyId: $this->getAuthenticatedCompanyId($request),
            companyUserId: (int) $request->user()->id,
            opportunityId: $opportunityId,
            applicationId: $applicationId,
            data: $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'تمت جدولة المقابلة وفتح المحادثة بنجاح. | Interview scheduled and conversation opened successfully.',
            'data' => new OpportunityInterviewResource($interview),
        ], 201);
    }

    public function reschedule(
        RescheduleOpportunityInterviewRequest $request,
        int $opportunityId,
        int $interviewId
    ): JsonResponse {
        $interview = $this->interviewService->reschedule(
            companyId: $this->getAuthenticatedCompanyId($request),
            opportunityId: $opportunityId,
            interviewId: $interviewId,
            data: $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'تمت إعادة جدولة المقابلة بنجاح. | Interview rescheduled successfully.',
            'data' => new OpportunityInterviewResource($interview),
        ]);
    }

    public function cancel(
        Request $request,
        int $opportunityId,
        int $interviewId
    ): JsonResponse {
        $interview = $this->interviewService->cancel(
            companyId: $this->getAuthenticatedCompanyId($request),
            opportunityId: $opportunityId,
            interviewId: $interviewId
        );

        return response()->json([
            'success' => true,
            'message' => 'تم إلغاء المقابلة بنجاح. | Interview cancelled successfully.',
            'data' => new OpportunityInterviewResource($interview),
        ]);
    }

    public function complete(
        CompleteOpportunityInterviewRequest $request,
        int $opportunityId,
        int $interviewId
    ): JsonResponse {
        $interview = $this->interviewService->complete(
            companyId: $this->getAuthenticatedCompanyId($request),
            opportunityId: $opportunityId,
            interviewId: $interviewId,
            data: $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'تم إنهاء المقابلة بنجاح. | Interview completed successfully.',
            'data' => new OpportunityInterviewResource($interview),
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
