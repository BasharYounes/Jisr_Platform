<?php

namespace App\Services\Recommendations;

use App\Models\AssessmentSession;

class LearningPathService
{
    public function __construct(
        private readonly SkillGapService $gapService,
        private readonly LearningRecommendationService $recommendationService
    ) {
    }

    public function generate(AssessmentSession $session): array
    {
        $gaps = $this->gapService->calculateForSession($session);

        return collect($gaps)
            ->where('gap', '>', 0)
            ->sortByDesc('gap')
            ->map(function ($gap) {
                return [
                    'skill_id' => $gap['skill_id'],
                    'skill_name' => $gap['skill_name'],
                    'current_level' => $gap['actual_level'],
                    'target_level' => $gap['required_level'],
                    'priority' => $gap['priority'],
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
}
