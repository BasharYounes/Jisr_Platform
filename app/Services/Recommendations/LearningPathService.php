<?php

namespace App\Services\Recommendations;

use App\Models\AssessmentSession;

class LearningPathService
{
    public function __construct(
        private readonly SkillGapService $gapService,
        private readonly LearningRecommendationService $recommendationService
    ) {}

    public function generate(AssessmentSession $session): array
    {
        $gaps = $this->gapService->calculateForSession($session);

        return collect($gaps)
            ->where('gap', '>', 0)
            ->sortByDesc(fn ($gap) => $this->resolveLearningPriorityScore($gap))
            ->map(function ($gap) {
                return [
                    'skill_id' => $gap['skill_id'],
                    'skill_name' => $gap['skill_name'],
                    'current_level' => $gap['actual_level'],
                    'target_level' => $gap['required_level'],
                    'priority' => $gap['priority'],

                    'market' => $gap['market'] ?? null,

                    'confidence_score' => $gap['confidence_score'] ?? null,
                    'topic_coverage_ratio' => $gap['topic_coverage_ratio'] ?? null,
                    'tested_topics' => $gap['tested_topics'] ?? [],
                    'improvement_topics' => $gap['improvement_topics'] ?? [],
                    'assessment_reliability' => $gap['assessment_reliability'] ?? 'غير محددة',

                    'resources' => $this->recommendationService->recommend(
                        $gap['skill_id'],
                        $gap['actual_level'],
                        $gap['required_level']
                    ),
                ];
            })
            ->values()
            ->toArray();
    }

    private function resolveLearningPriorityScore(array $gap): float
    {
        $gapScore = ((float) $gap['gap']) * 100;

        $marketScore = isset($gap['market']['demand_score'])
            ? ((float) $gap['market']['demand_score']) / 100
            : 0;

        return $gapScore + $marketScore;
    }
}
