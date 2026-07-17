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
use App\Services\Assessment\AssessmentExpertEvaluationOrchestratorService;

class AssessmentAnswerController extends Controller
{
    public function __construct(
        private readonly AnswerEvaluationService $answerEvaluationService,
        private readonly AssessmentExpertEvaluationOrchestratorService $expertEvaluationOrchestrator,
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

        $usesExpertRules =
            $attempt->questionBank->EvaluationEngine === 'expert_rules'
            && (bool) $attempt->questionBank->IsExpertReady;

        /*
        * Legacy path variables.
        * They are populated only when expert_rules is not active.
        */
        $rawEvaluation = null;
        $evaluation = [];
        $normalizedScore = 0.0;
        $needsReview = false;

        if (! $usesExpertRules) {
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
        }

        $submission = DB::transaction(function () use (
            $attempt,
            $answerText,
            $levelBefore,
            $usesExpertRules,
            $rawEvaluation,
            $evaluation,
            $normalizedScore,
            $needsReview
        ) {
            $evaluationRunId = null;

            /*
            * Expert path:
            * Gemini extracts evidence only.
            * Laravel Expert Rule Engine calculates the score.
            */
            if ($usesExpertRules) {
                $expertResult = $this->expertEvaluationOrchestrator
                    ->evaluateAndPersist(
                        attempt: $attempt,
                        studentAnswer: $answerText,
                    );

                $evaluation = $expertResult['evaluation'];
                $evaluationRunId = (int) $expertResult['evaluation_run_id'];

                $normalizedScore = (float) (
                    $evaluation['normalized_score'] ?? 0
                );

                /*
                * Expert rules do not use Gemini confidence or
                * EvaluationValidationService review logic.
                */
                $needsReview = false;
                $rawEvaluation = null;
            }

            $attemptEvaluationJson = $usesExpertRules
                ? array_merge($evaluation, [
                    'evaluation_mode' => 'expert_rules',
                    'evaluation_run_id' => $evaluationRunId,
                ])
                : $evaluation;

            AssessmentAnswer::query()->create([
                'AssessmentQuestionAttemptID' => (
                    $attempt->AssessmentQuestionAttemptID
                ),
                'AnswerText' => $answerText,
                'AnswerJson' => null,
                'SubmittedAt' => now(),
            ]);

            $attempt->update([
                'AnsweredAt' => now(),

                /*
                * Kept for compatibility because the rest of the system
                * currently relies on LlmEvaluationStatus.
                */
                'LlmEvaluationStatus' => $needsReview
                    ? 'needs_review'
                    : 'completed',

                'RawScore' => (float) ($evaluation['total_score'] ?? 0),
                'NormalizedScore' => $normalizedScore,
                'FeedbackText' => $evaluation['feedback_ar'] ?? null,
                'EvaluationJson' => $attemptEvaluationJson,

                /*
                * New explicit evaluation metadata for expert rules only.
                * Legacy attempts remain unchanged.
                */
                'EvaluationEngine' => $usesExpertRules
                    ? 'expert_rules'
                    : $attempt->EvaluationEngine,

                'EvaluationStatus' => $usesExpertRules
                    ? 'completed'
                    : $attempt->EvaluationStatus,

                'EvaluationEngineVersion' => $usesExpertRules
                    ? $attempt->questionBank->RuleSetVersion
                    : $attempt->EvaluationEngineVersion,
            ]);

            $skillSession = $attempt->assessmentSkillSession;

            if ($needsReview) {
                $newLevel = (float) $skillSession->CurrentEstimatedLevel;
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

            $levelAfter = $attempt->assessmentSkillSession
                ->fresh()
                ->CurrentEstimatedLevel;

            $this->telemetryService->record([
                'assessment_session_id' => (
                    $attempt->assessmentSkillSession->AssessmentSessionID ?? null
                ),
                'assessment_skill_session_id' => (
                    $attempt->assessmentSkillSession->AssessmentSkillSessionID
                ),
                'assessment_question_attempt_id' => (
                    $attempt->AssessmentQuestionAttemptID
                ),
                'question_id' => $attempt->QuestionID,

                'event_type' => 'answer_evaluated',

                'level_before' => $levelBefore,
                'level_after' => $levelAfter,

                'normalized_score' => $evaluation['normalized_score'] ?? null,

                /*
                * null for expert_rules.
                * Gemini never supplies confidence in this path.
                */
                'confidence_score' => $evaluation['confidence'] ?? null,

                'payload' => [
                    'question_level' => $attempt->questionBank->Level ?? null,
                    'difficulty_weight' => (
                        $attempt->questionBank->DifficultyWeight ?? null
                    ),
                    'feedback' => $evaluation['feedback_ar'] ?? null,
                    'validation' => $evaluation['validation'] ?? null,
                    'raw_evaluation' => $rawEvaluation,
                    'validated_evaluation' => $evaluation,
                    'evaluation_mode' => $usesExpertRules
                        ? 'expert_rules'
                        : 'legacy_llm',
                    'evaluation_run_id' => $evaluationRunId,
                ],
            ]);

            return [
                'evaluation' => $evaluation,
                'normalized_score' => $normalizedScore,
                'needs_review' => $needsReview,
            ];
        });

        $evaluation = $submission['evaluation'];
        $normalizedScore = (float) $submission['normalized_score'];
        $needsReview = (bool) $submission['needs_review'];

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
