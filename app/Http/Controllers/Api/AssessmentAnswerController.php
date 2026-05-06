<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitAnswerRequest;
use App\Models\AssessmentAnswer;
use App\Models\AssessmentQuestionAttempt;
use App\Services\AI\AnswerEvaluationService;
use App\Services\Assessment\LevelEstimationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Support\ApiResponse;

class AssessmentAnswerController extends Controller
{
    public function __construct(
        private readonly AnswerEvaluationService $answerEvaluationService,
        private readonly LevelEstimationService $levelEstimationService
    ) {
    }

    public function submit(SubmitAnswerRequest $request, $session, AssessmentQuestionAttempt $attempt): JsonResponse
    {
        $attempt->load([
            'questionBank.rubrics',
            'questionBank.skill',
            'assessmentSkillSession.assessmentSession',
        ]);

        if ($attempt->assessmentSkillSession->assessmentSession->UserID !== auth()->id()) {
            return ApiResponse::error('Unauthorized access to this attempt.', 403);
        }

        if ($attempt->answer) {
            return ApiResponse::error('This attempt has already been answered.', 422);
        }

        $answerText = $request->answer_text;

        $evaluation = $this->answerEvaluationService->evaluate(
            $attempt->questionBank,
            $answerText
        );

        $normalizedScore = (float) ($evaluation['normalized_score'] ?? 0);

        DB::transaction(function () use ($attempt, $answerText, $evaluation, $normalizedScore) {
            AssessmentAnswer::query()->create([
                'AssessmentQuestionAttemptID' => $attempt->AssessmentQuestionAttemptID,
                'AnswerText' => $answerText,
                'AnswerJson' => null,
                'SubmittedAt' => now(),
            ]);

            $attempt->update([
                'AnsweredAt' => now(),
                'LlmEvaluationStatus' => 'completed',
                'RawScore' => (float) ($evaluation['total_score'] ?? 0),
                'NormalizedScore' => $normalizedScore,
                'FeedbackText' => $evaluation['feedback_ar'] ?? null,
                'EvaluationJson' => $evaluation,
            ]);

            $skillSession = $attempt->assessmentSkillSession;

            $newLevel = $this->levelEstimationService->resolveNextLevel(
                (float) $skillSession->CurrentEstimatedLevel,
                $normalizedScore
            );

            $skillSession->update([
                'CurrentEstimatedLevel' => $newLevel,
                'QuestionCount' => $skillSession->QuestionCount + 1,
            ]);
        });

        return ApiResponse::success('Answer submitted and evaluated successfully.', [
            'attempt_id' => $attempt->AssessmentQuestionAttemptID,
            'normalized_score' => $normalizedScore,
            'feedback' => $evaluation['feedback_ar'] ?? null,
        ]);
    }

    public function result($session, AssessmentQuestionAttempt $attempt): JsonResponse
    {
        return ApiResponse::success('Result retrieved successfully.', $attempt->load(['answer', 'questionBank', 'assessmentSkillSession']));
    }
}
