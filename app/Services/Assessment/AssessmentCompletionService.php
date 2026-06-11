<?php

namespace App\Services\Assessment;

use App\Models\AssessmentSkillSession;
use App\Models\QuestionBank;

class AssessmentCompletionService
{
    private const MIN_QUESTIONS = 5;

    private const MAX_QUESTIONS = 10;

    private const REQUIRED_CONFIDENCE = 0.70;

    private const EXPECTED_TOPICS_FOR_CONFIDENCE = 3;

    private const MIN_TOPIC_COVERAGE_CONFIDENCE_FACTOR = 0.85;

    public function __construct(
        private readonly LevelEstimationService $levelEstimationService
    ) {}

    public function completeSkillSessionIfEligible(AssessmentSkillSession $skillSession): AssessmentSkillSession
    {
        $skillSession->loadMissing([
            'questionAttempts.questionBank',
            'assessmentSession',
        ]);

        if ($this->isAlreadyCompleted($skillSession)) {
            return $skillSession->fresh([
                'questionAttempts.questionBank',
                'assessmentSession',
            ]);
        }

        $attempts = $this->extractEvaluatedAttempts($skillSession);

        if (! $this->shouldComplete($attempts)) {
            return $skillSession->fresh([
                'questionAttempts.questionBank',
                'assessmentSession',
            ]);
        }

        $startingLevel = $this->resolveStartingLevel($skillSession);

        $skillSession->forceFill([
            'FinalLevel' => $this->levelEstimationService->resolveFinalLevelFromAttempts(
                attempts: $attempts,
                startingLevel: $startingLevel
            ),
            'ConfidenceScore' => $this->calculateTopicAdjustedConfidence(
                skillSession: $skillSession,
                attempts: $attempts
            ),
            'Status' => 'completed',
            'CompletedAt' => now(),
        ])->save();

        return $skillSession->fresh([
            'questionAttempts.questionBank',
            'assessmentSession',
        ]);
    }

    public function shouldStopAsking(AssessmentSkillSession $skillSession): bool
    {
        $skillSession->loadMissing([
            'questionAttempts.questionBank',
            'assessmentSession',
        ]);

        if ($this->isAlreadyCompleted($skillSession)) {
            return true;
        }

        $attempts = $this->extractEvaluatedAttempts($skillSession);

        return $this->shouldComplete($attempts);
    }

    public function resolveCompletionReason(AssessmentSkillSession $skillSession): string
    {
        $skillSession->loadMissing([
            'questionAttempts.questionBank',
            'assessmentSession',
        ]);

        if ($this->isAlreadyCompleted($skillSession)) {
            return 'already_completed';
        }

        $attempts = $this->extractEvaluatedAttempts($skillSession);

        $questionCount = count($attempts);

        if ($questionCount < self::MIN_QUESTIONS) {
            return 'not_completed';
        }

        if ($questionCount >= self::MAX_QUESTIONS) {
            return 'max_questions_reached';
        }

        $confidence = $this->levelEstimationService
            ->calculateConfidenceFromAttempts($attempts);

        if ($confidence >= self::REQUIRED_CONFIDENCE) {
            return 'confidence_threshold_reached';
        }

        return 'not_completed';
    }

    private function shouldComplete(array $attempts): bool
    {
        $questionCount = count($attempts);

        if ($questionCount < self::MIN_QUESTIONS) {
            return false;
        }

        if ($questionCount >= self::MAX_QUESTIONS) {
            return true;
        }

        $confidence = $this->levelEstimationService
            ->calculateConfidenceFromAttempts($attempts);

        return $confidence >= self::REQUIRED_CONFIDENCE;
    }

    private function extractEvaluatedAttempts(AssessmentSkillSession $skillSession): array
    {
        return $skillSession->questionAttempts
            ->sortBy('AskedAt')
            ->filter(fn ($attempt) => $attempt->NormalizedScore !== null && $attempt->NormalizedScore !== '')
            ->map(function ($attempt) {
                return [
                    'score' => (float) $attempt->NormalizedScore,
                    'question_level' => (float) (
                        $attempt->QuestionLevel
                        ?? $attempt->questionBank?->Level
                        ?? 1
                    ),
                    'difficulty_weight' => (float) (
                        $attempt->questionBank?->DifficultyWeight
                        ?? 1.0
                    ),
                ];
            })
            ->values()
            ->all();
    }

    private function isAlreadyCompleted(AssessmentSkillSession $skillSession): bool
    {
        return $skillSession->Status === 'completed'
            && $skillSession->FinalLevel !== null;
    }

    private function resolveStartingLevel(AssessmentSkillSession $skillSession): float
    {
        $startingLevel = (float) (
            $skillSession->InitialLevel
            ?: $skillSession->CurrentEstimatedLevel
            ?: 1
        );

        return max(1.0, min(5.0, $startingLevel));
    }

    /**
     * Adjusts the confidence score based on topic coverage.
     *
     * The goal is not to reward higher confidence, but to slightly reduce
     * confidence when the assessment covers too few topics compared to
     * the available topic diversity in the question bank.
     */
    public function calculateTopicAdjustedConfidence(
        AssessmentSkillSession $skillSession,
        array $attempts
    ): float {
        $baseConfidence = $this->levelEstimationService
            ->calculateConfidenceFromAttempts($attempts);

        $skillSession->loadMissing('questionAttempts.questionBank');

        $testedTopicCount = $skillSession->questionAttempts
            ->pluck('questionBank.Topic')
            ->filter()
            ->unique()
            ->count();

        $availableTopicCount = QuestionBank::query()
            ->where('SkillID', $skillSession->SkillID)
            ->where('IsActive', true)
            ->whereNotNull('Topic')
            ->distinct()
            ->count('Topic');

        if ($availableTopicCount <= 1) {
            return $baseConfidence;
        }

        $expectedTopicCount = min(
            self::EXPECTED_TOPICS_FOR_CONFIDENCE,
            $availableTopicCount
        );

        $topicFactor = min(1.0, $testedTopicCount / $expectedTopicCount);

        $confidenceFactor =
            self::MIN_TOPIC_COVERAGE_CONFIDENCE_FACTOR
            + ((1 - self::MIN_TOPIC_COVERAGE_CONFIDENCE_FACTOR) * $topicFactor);

        return round(max(0.0, min(1.0, $baseConfidence * $confidenceFactor)), 2);
    }
}
