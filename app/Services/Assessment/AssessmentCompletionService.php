<?php

namespace App\Services\Assessment;

use App\Models\AssessmentSkillSession;

class AssessmentCompletionService
{
    public function __construct(
        private readonly LevelEstimationService $levelEstimationService
    ) {
    }

    public function completeSkillSessionIfEligible(AssessmentSkillSession $skillSession): AssessmentSkillSession
    {
        $skillSession->loadMissing('questionAttempts');

        if ($this->isAlreadyCompleted($skillSession)) {
            return $skillSession->fresh(['questionAttempts', 'assessmentSession']);
        }

        $scores = $this->extractNormalizedScores($skillSession);

        if (count($scores) < 3) {
            return $skillSession->fresh(['questionAttempts', 'assessmentSession']);
        }
        
        $startingLevel = $this->resolveStartingLevel($skillSession);

        $skillSession->forceFill([
            'FinalLevel' => $this->levelEstimationService->resolveFinalLevel($scores, $startingLevel),
            'ConfidenceScore' => $this->levelEstimationService->calculateConfidence(count($scores), $scores),
            'Status' => 'completed',
            'CompletedAt' => now(),
        ])->save();

        return $skillSession->fresh(['questionAttempts', 'assessmentSession']);
    }

    private function isAlreadyCompleted(AssessmentSkillSession $skillSession): bool
    {
        return $skillSession->Status === 'completed' && $skillSession->FinalLevel !== null;
    }

    private function resolveStartingLevel(AssessmentSkillSession $skillSession): float
    {
        $startingLevel = (float) ($skillSession->CurrentEstimatedLevel ?: $skillSession->InitialLevel ?: 1);

        return max(1.0, min(5.0, $startingLevel));
    }

    private function extractNormalizedScores(AssessmentSkillSession $skillSession): array
    {
        return $skillSession->questionAttempts
            ->sortBy('AskedAt')
            ->pluck('NormalizedScore')
            ->filter(static fn ($score) => $score !== null && $score !== '')
            ->map(static fn ($score) => (float) $score)
            ->values()
            ->all();
    }
}
