<?php

namespace App\Services\MarketAnalysis;

use Illuminate\Support\Facades\DB;

final class MarketDataQualityService
{
    public function getSummary(): array
    {
        $totalJobPostings = DB::table('market_job_postings')->count();

        $classifiedJobPostings = DB::table('market_job_postings')
            ->whereNotNull('career_path_id')
            ->count();

        $unclassifiedJobPostings =
            $totalJobPostings - $classifiedJobPostings;

        $rawClassificationCounts = DB::table(
            'market_job_postings'
        )
            ->select([
                'classification_status',
                DB::raw('COUNT(*) as total'),
            ])
            ->groupBy('classification_status')
            ->pluck('total', 'classification_status')
            ->map(
                fn ($count): int => (int) $count
            )
            ->all();

        $classificationStatuses = [];

        foreach ([
            'pending',
            'classified',
            'ambiguous',
            'out_of_scope',
            'insufficient_evidence',
        ] as $classificationStatus) {
            $count = (int) (
                $rawClassificationCounts[
                    $classificationStatus
                ] ?? 0
            );

            $classificationStatuses[
                $classificationStatus
            ] = [
                'count' => $count,

                'percentage' => $this->percentage(
                    $count,
                    $totalJobPostings
                ),
            ];
        }

        $analyzedJobPostings = max(
            0,
            $totalJobPostings -
            $classificationStatuses['pending']['count']
        );

        $jobPostingsWithSkills = DB::table(
            'market_job_posting_skill_occurrences'
        )
            ->distinct()
            ->count('market_job_posting_id');

        $jobPostingsWithoutSkills = max(
            0,
            $totalJobPostings - $jobPostingsWithSkills
        );

        $totalSkillOccurrences = DB::table(
            'market_job_posting_skill_occurrences'
        )->count();

        $uniqueExtractedSkills = DB::table(
            'market_job_posting_skill_occurrences'
        )
            ->distinct()
            ->count('skill_id');

        $sources = DB::table('market_job_postings')
            ->select([
                'source_name',
                DB::raw('COUNT(*) as job_postings_count'),
            ])
            ->groupBy('source_name')
            ->orderByDesc('job_postings_count')
            ->get()
            ->map(fn (object $source): array => [
                'source_name' => $source->source_name ?? 'unknown',
                'job_postings_count' =>
                    (int) $source->job_postings_count,
            ])
            ->values()
            ->all();

        return [
            'job_postings' => [
                'total' => $totalJobPostings,
                'classified' => $classifiedJobPostings,
                'unclassified' => $unclassifiedJobPostings,

                'classified_percentage' =>
                    $this->percentage(
                        $classifiedJobPostings,
                        $totalJobPostings
                    ),
            ],

            'classification' => [
                /*
                * النسخة الحالية من قواعد التصنيف.
                */
                'current_method' => (string) config(
                    'market_analysis_classifier.method',
                    'weighted_rules_v1'
                ),

                /*
                * عدد الإعلانات التي خرجت من حالة pending.
                */
                'analyzed_job_postings' =>
                    $analyzedJobPostings,

                'analysis_coverage_percentage' =>
                    $this->percentage(
                        $analyzedJobPostings,
                        $totalJobPostings
                    ),

                'statuses' => $classificationStatuses,
            ],

            'skill_extraction' => [
                'job_postings_with_skills' =>
                    $jobPostingsWithSkills,

                'job_postings_without_skills' =>
                    $jobPostingsWithoutSkills,

                'coverage_percentage' => $this->percentage(
                    $jobPostingsWithSkills,
                    $totalJobPostings
                ),

                'total_occurrences' => $totalSkillOccurrences,
                'unique_skills' => $uniqueExtractedSkills,
            ],

            'sources' => $sources,

            'latest_activity' => [
                'latest_job_update_at' => DB::table(
                    'market_job_postings'
                )->max('updated_at'),

                'latest_published_at' => DB::table(
                    'market_job_postings'
                )->max('published_at'),
            ],
        ];
    }

    private function percentage(int $part, int $total): float
    {
        if ($total === 0) {
            return 0.0;
        }

        return round(($part / $total) * 100, 2);
    }
}
