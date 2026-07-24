<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssessmentSession;
use App\Services\Recommendations\SkillGapService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecommendationController extends Controller
{
    public function __construct(
        private readonly SkillGapService $skillGapService
    ) {}

    public function skillGaps(AssessmentSession $session, Request $request): JsonResponse
    {
        if ((int) $session->UserID !== (int) $request->user()->id) {
            return ApiResponse::error('Unauthorized access to this assessment session.', 403);
        }

        return ApiResponse::success('Skill gaps calculated successfully.', [
            'assessment_session_id' => $session->AssessmentSessionID,
            'gaps' => $this->skillGapService->calculateForSession($session),
        ]);
    }
}
