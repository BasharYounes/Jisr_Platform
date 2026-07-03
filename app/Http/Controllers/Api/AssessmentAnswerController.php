<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitAnswerRequest;
use App\Models\AssessmentAnswer;
use App\Models\AssessmentQuestionAttempt;
use App\Services\AI\AnswerEvaluationService;
use App\Services\Assessment\AssessmentTelemetryService;
use App\Services\Assessment\EvaluationValidationService;
use App\Services\Assessment\LevelEstimationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AssessmentAnswerController extends Controller
{
    public function __construct(
        private readonly AnswerEvaluationService $answerEvaluationService,
        private readonly LevelEstimationService $levelEstimationService,
        private readonly AssessmentTelemetryService $telemetryService,
        private readonly EvaluationValidationService $evaluationValidationService
    ) {}

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

        $levelBefore = $attempt->assessmentSkillSession->CurrentEstimatedLevel;

        $rawEvaluation = $this->answerEvaluationService->evaluate(
            $attempt->questionBank,
            $answerText
        );

        $evaluation = $this->evaluationValidationService->validateAndNormalize(
            $attempt->questionBank,
            $rawEvaluation
        );

        $normalizedScore = (float) $evaluation['normalized_score'];

        $needsReview = (bool) data_get(
            $evaluation,
            'validation.needs_review',
            false
        );

        DB::transaction(function () use (
            $attempt,
            $answerText,
            $evaluation,
            $normalizedScore,
            $levelBefore,
            $rawEvaluation,
            $needsReview
        ) {

            AssessmentAnswer::query()->create([
                'AssessmentQuestionAttemptID' => $attempt->AssessmentQuestionAttemptID,
                'AnswerText' => $answerText,
                'AnswerJson' => null,
                'SubmittedAt' => now(),
            ]);

            $attempt->update([
                'AnsweredAt' => now(),
                'LlmEvaluationStatus' => $needsReview
                    ? 'needs_review'
                    : 'completed',
                'RawScore' => (float) ($evaluation['total_score'] ?? 0),
                'NormalizedScore' => $normalizedScore,
                'FeedbackText' => $evaluation['feedback_ar'] ?? null,
                'EvaluationJson' => $evaluation,
            ]);

            $skillSession = $attempt->assessmentSkillSession;

            if ($needsReview) {
                $newLevel = (float) $skillSession->CurrentEstimatedLevel;

                // $skillSession->update([
                //     'QuestionCount' => $skillSession->QuestionCount + 1,
                // ]);
            } else {
                $newLevel = $this->levelEstimationService->resolveNextLevel(
                    (float) $skillSession->CurrentEstimatedLevel,
                    $normalizedScore
                );

                $skillSession->update([
                    'CurrentEstimatedLevel' => $newLevel,
                    'QuestionCount' => $skillSession->QuestionCount + 1,
                ]);
            }

            $levelAfter = $attempt->assessmentSkillSession->fresh()->CurrentEstimatedLevel;

            $this->telemetryService->record([
                'assessment_session_id' => $attempt->assessmentSkillSession->AssessmentSessionID ?? null,
                'assessment_skill_session_id' => $attempt->assessmentSkillSession->AssessmentSkillSessionID,
                'assessment_question_attempt_id' => $attempt->AssessmentQuestionAttemptID,
                'question_id' => $attempt->QuestionID,

                'event_type' => 'answer_evaluated',

                'level_before' => $levelBefore,
                'level_after' => $levelAfter,

                'normalized_score' => $evaluation['normalized_score'] ?? null,
                'confidence_score' => $evaluation['confidence'] ?? null,

                'payload' => [
                    'question_level' => $attempt->questionBank->Level ?? null,
                    'difficulty_weight' => $attempt->questionBank->DifficultyWeight ?? null,
                    'feedback' => $evaluation['feedback_ar'] ?? null,

                    'validation' => $evaluation['validation'] ?? null,

                    'raw_evaluation' => $rawEvaluation,
                    'validated_evaluation' => $evaluation,
                ],
            ]);
        });

        $message = $needsReview
        ? 'Answer submitted and flagged for review.'
        : 'Answer submitted and evaluated successfully.';

        return ApiResponse::success($message, [
            'attempt_id' => $attempt->AssessmentQuestionAttemptID,
            'normalized_score' => $normalizedScore,
            'feedback' => $evaluation['feedback_ar'] ?? null,
            'needs_review' => $needsReview,
        ]);
    }

    public function result($session, AssessmentQuestionAttempt $attempt): JsonResponse
    {
        return ApiResponse::success('Result retrieved successfully.', $attempt->load(['answer', 'questionBank', 'assessmentSkillSession']));
    }
}
