<?php

namespace App\Services\MarketAnalysis;

use App\Models\MarketTrend;
use Illuminate\Support\Carbon;

class MarketTrendSnapshotService
{
    public function __construct(
        private readonly MarketInsightsService $marketInsightsService
    ) {}

    public function snapshotCareerPath(
        int $careerPathId,
        ?Carbon $analyzedDate = null
    ): array {
        $analyzedDate = $analyzedDate?->copy()->startOfDay() ?? now()->startOfDay();

        $insights = $this->marketInsightsService
            ->getSkillDemandByCareerPath($careerPathId);

        $saved = 0;

        foreach ($insights['skills'] as $skill) {
            $previousTrend = MarketTrend::query()
                ->where('career_path_id', $careerPathId)
                ->where('skill_id', $skill['skill_id'])
                ->whereDate('analyzed_date', '<', $analyzedDate->toDateString())
                ->orderByDesc('analyzed_date')
                ->first();

            $trendDirection = $this->detectTrendDirection(
                previousScore: $previousTrend?->demand_score,
                currentScore: (float) $skill['demand_percentage']
            );

            MarketTrend::query()->updateOrCreate(
                [
                    'career_path_id' => $careerPathId,
                    'skill_id' => $skill['skill_id'],
                    'analyzed_date' => $analyzedDate->toDateString(),
                ],
                [
                    'demand_score' => $skill['demand_percentage'],
                    'trend_direction' => $trendDirection,
                    'source_job_count' => $skill['job_posting_count'],
                ]
            );

            $saved++;
        }

        return [
            'career_path_id' => $careerPathId,
            'analyzed_date' => $analyzedDate->toDateString(),
            'total_job_postings' => $insights['total_job_postings'],
            'saved_trends' => $saved,
        ];
    }

    private function detectTrendDirection(?float $previousScore, float $currentScore): string
    {
        if ($previousScore === null) {
            return 'new';
        }

        $difference = $currentScore - $previousScore;

        if ($difference >= 2.0) {
            return 'rising';
        }

        if ($difference <= -2.0) {
            return 'falling';
        }

        return 'stable';
    }
}
