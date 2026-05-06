<?php

namespace App\Services\Assessment;

use App\Models\AssessmentSkillSession;

class AssessmentCompletionService
{
    private const MIN_QUESTIONS = 3;
    private const MAX_QUESTIONS = 7;
    private const REQUIRED_CONFIDENCE = 0.75;

    public function __construct(
        private readonly LevelEstimationService $levelEstimationService
    ) {
    }

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
            'ConfidenceScore' => $this->levelEstimationService->calculateConfidenceFromAttempts($attempts),
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
}
