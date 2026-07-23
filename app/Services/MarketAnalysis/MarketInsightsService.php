<?php

namespace App\Services\MarketAnalysis;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MarketInsightsService
{
    /**
     * Calculate skill demand for a specific career path.
     */
    public function getSkillDemandByCareerPath(
        int $careerPathId,
        ?Carbon $from = null,
        ?Carbon $to = null
    ): array {
        $totalJobPostings = $this->countJobPostings($careerPathId, $from, $to);

        if ($totalJobPostings === 0) {
            return [
                'career_path_id' => $careerPathId,
                'total_job_postings' => 0,
                'skills' => collect(),
                'skill_map' => collect(),
            ];
        }

        $skills = DB::table('market_job_posting_skill_occurrences as occurrences')
            ->join('market_job_postings as postings', 'postings.id', '=', 'occurrences.market_job_posting_id')
            ->join('skills', 'skills.id', '=', 'occurrences.skill_id')
            ->where('postings.career_path_id', $careerPathId)
            ->where('postings.status', 'active')
            ->when($from, function ($query) use ($from) {
                $query->where(function ($subQuery) use ($from) {
                    $subQuery->whereNull('postings.published_at')
                        ->orWhere('postings.published_at', '>=', $from);
                });
            })
            ->when($to, function ($query) use ($to) {
                $query->where(function ($subQuery) use ($to) {
                    $subQuery->whereNull('postings.published_at')
                        ->orWhere('postings.published_at', '<=', $to);
                });
            })
            ->select([
                'skills.id as skill_id',
                'skills.name as skill_name',
                'skills.category as skill_category',
                DB::raw('COUNT(DISTINCT postings.id) as job_posting_count'),
            ])
            ->groupBy('skills.id', 'skills.name', 'skills.category')
            ->orderByDesc('job_posting_count')
            ->get()
            ->map(function ($row) use ($totalJobPostings) {
                $percentage = round(($row->job_posting_count / $totalJobPostings) * 100, 2);

                return [
                    'skill_id' => (int) $row->skill_id,
                    'skill_name' => $row->skill_name,
                    'skill_category' => $row->skill_category,
                    'job_posting_count' => (int) $row->job_posting_count,
                    'demand_percentage' => $percentage,
                    'demand_level' => $this->classifyDemandLevel($percentage),
                ];
            });

        return [
            'career_path_id' => $careerPathId,
            'total_job_postings' => $totalJobPostings,
            'skills' => $skills,
            'skill_map' => $this->buildSkillMap($skills),
        ];
    }

    public function getCareerPathsForMarketAnalysis(bool $onlyWithMarketData = false): Collection
    {
        return DB::table('career_paths')
            ->leftJoin('market_job_postings as postings', function ($join) {
                $join->on('career_paths.CareerPathID', '=', 'postings.career_path_id')
                    ->where('postings.status', '=', 'active');
            })
            ->leftJoin('market_trends as trends', 'career_paths.CareerPathID', '=', 'trends.career_path_id')
            ->select([
                'career_paths.CareerPathID as id',
                'career_paths.Name as name',
                'career_paths.Description as description',
                DB::raw('COUNT(DISTINCT postings.id) as total_job_postings'),
                DB::raw('MAX(trends.analyzed_date) as latest_snapshot_date'),
            ])
            ->groupBy(
                'career_paths.CareerPathID',
                'career_paths.Name',
                'career_paths.Description'
            )
            ->when($onlyWithMarketData, function ($query) {
                $query->havingRaw('COUNT(DISTINCT postings.id) > 0');
            })
            ->orderBy('career_paths.Name')
            ->get()
            ->map(function ($row) {
                return [
                    'id' => (int) $row->id,
                    'name' => $row->name,
                    'description' => $row->description,
                    'total_job_postings' => (int) $row->total_job_postings,
                    'latest_snapshot_date' => $row->latest_snapshot_date,
                    'has_market_data' => ((int) $row->total_job_postings) > 0,
                ];
            });
    }

    public function getLatestTrendSnapshotDate(int $careerPathId): ?string
    {
        return DB::table('market_trends')
            ->where('career_path_id', $careerPathId)
            ->max('analyzed_date');
    }

    public function getTrendSnapshot(
        int $careerPathId,
        string $analyzedDate
    ): Collection {
        return DB::table('market_trends')
            ->join('skills', 'skills.id', '=', 'market_trends.skill_id')
            ->where('market_trends.career_path_id', $careerPathId)
            ->whereDate('market_trends.analyzed_date', $analyzedDate)
            ->select([
                'market_trends.skill_id',
                'skills.name as skill_name',
                'skills.category as skill_category',
                'market_trends.demand_score',
                'market_trends.trend_direction',
                'market_trends.source_job_count',
                'market_trends.analyzed_date',
            ])
            ->orderByDesc('market_trends.demand_score')
            ->get()
            ->map(function ($row) {
                return [
                    'skill_id' => (int) $row->skill_id,
                    'skill_name' => $row->skill_name,
                    'skill_category' => $row->skill_category,
                    'demand_score' => (float) $row->demand_score,
                    'trend_direction' => $row->trend_direction,
                    'source_job_count' => (int) $row->source_job_count,
                    'analyzed_date' => $row->analyzed_date,
                ];
            });
    }

    public function getSkillEvidence(
        int $careerPathId,
        int $skillId,
        int $limit = 10
    ): Collection {
        return DB::table('market_job_posting_skill_occurrences as occurrences')
            ->join('market_job_postings as postings', 'postings.id', '=', 'occurrences.market_job_posting_id')
            ->join('skills', 'skills.id', '=', 'occurrences.skill_id')
            ->leftJoin('skill_aliases', 'skill_aliases.SkillAliasID', '=', 'occurrences.skill_alias_id')
            ->where('postings.career_path_id', $careerPathId)
            ->where('postings.status', 'active')
            ->where('occurrences.skill_id', $skillId)
            ->select([
                'postings.id as job_posting_id',
                'postings.title',
                'postings.company_name',
                'postings.location',
                'postings.language as posting_language',
                'postings.source_type',
                'postings.source_name',
                'postings.url',
                'postings.published_at',
                'skills.id as skill_id',
                'skills.name as skill_name',
                'skills.category as skill_category',
                'occurrences.matched_text',
                'occurrences.language as matched_language',
                'occurrences.extraction_method',
                'occurrences.confidence',
                'occurrences.context',
                'skill_aliases.Alias as alias',
            ])
            ->orderByDesc('postings.published_at')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                return [
                    'job_posting' => [
                        'id' => (int) $row->job_posting_id,
                        'title' => $row->title,
                        'company_name' => $row->company_name,
                        'location' => $row->location,
                        'language' => $row->posting_language,
                        'source_type' => $row->source_type,
                        'source_name' => $row->source_name,
                        'url' => $row->url,
                        'published_at' => $row->published_at,
                    ],
                    'skill' => [
                        'id' => (int) $row->skill_id,
                        'name' => $row->skill_name,
                        'category' => $row->skill_category,
                    ],
                    'evidence' => [
                        'matched_text' => $row->matched_text,
                        'matched_language' => $row->matched_language,
                        'alias' => $row->alias,
                        'confidence' => (float) $row->confidence,
                        'extraction_method' => $row->extraction_method,
                        'context' => $row->context,
                    ],
                ];
            });
    }

    private function countJobPostings(
        int $careerPathId,
        ?Carbon $from = null,
        ?Carbon $to = null
    ): int {
        return DB::table('market_job_postings')
            ->where('career_path_id', $careerPathId)
            ->where('status', 'active')
            ->when($from, function ($query) use ($from) {
                $query->where(function ($subQuery) use ($from) {
                    $subQuery->whereNull('published_at')
                        ->orWhere('published_at', '>=', $from);
                });
            })
            ->when($to, function ($query) use ($to) {
                $query->where(function ($subQuery) use ($to) {
                    $subQuery->whereNull('published_at')
                        ->orWhere('published_at', '<=', $to);
                });
            })
            ->count();
    }

    private function classifyDemandLevel(float $percentage): string
    {
        if ($percentage >= 60) {
            return 'core';
        }

        if ($percentage >= 30) {
            return 'important';
        }

        return 'supporting';
    }

    private function buildSkillMap(Collection $skills): Collection
    {
        return $skills
            ->groupBy('skill_category')
            ->map(function (Collection $categorySkills) {
                return $categorySkills
                    ->values()
                    ->map(function (array $skill) {
                        return [
                            'skill_id' => $skill['skill_id'],
                            'skill_name' => $skill['skill_name'],
                            'job_posting_count' => $skill['job_posting_count'],
                            'demand_percentage' => $skill['demand_percentage'],
                            'demand_level' => $skill['demand_level'],
                        ];
                    });
            });
    }
}
