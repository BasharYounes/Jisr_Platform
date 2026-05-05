<?php

namespace App\Http\Controllers\Api\AI;

use App\Http\Controllers\Controller;
use App\Http\Requests\AI\GenerateAILearningPlanRequest;
use App\Models\AILearningPlan;
use App\Models\AssessmentSession;
use App\Services\Recommendations\AILearningPlanService;
use App\Support\ApiResponse;

class AILearningPlanController extends Controller
{
    public function __construct(
        private readonly AILearningPlanService $aiLearningPlanService
    ) {
    }

    public function generate(
        GenerateAILearningPlanRequest $request,
        AssessmentSession $session
    ) {
        if ($session->UserID !== auth()->id()) {
            return ApiResponse::error('Unauthorized access to this assessment session.', 403);
        }

        if ($session->Status !== 'completed') {
            return ApiResponse::error('Assessment session must be completed before generating a learning plan.', 422);
        }

        $plan = $this->aiLearningPlanService->generate(
            session: $session,
            weeks: (int) $request->input('weeks', 4),
            hoursPerWeek: (int) $request->input('hours_per_week', 5)
        );

        return ApiResponse::success('AI learning plan generated successfully.', $plan, 201);
    }

    public function latest(AssessmentSession $session)
    {
        if ($session->UserID !== auth()->id()) {
            return ApiResponse::error('Unauthorized access to this assessment session.', 403);
        }

        $plan = AILearningPlan::query()
            ->where('AssessmentSessionID', $session->AssessmentSessionID)
            ->latest('AILearningPlanID')
            ->first();

        if (!$plan) {
            return ApiResponse::error('No AI learning plan found for this assessment session.', 404);
        }

        return ApiResponse::success('AI learning plan retrieved successfully.', $plan);
    }
}
