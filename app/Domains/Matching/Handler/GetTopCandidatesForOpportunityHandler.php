<?php

namespace App\Domains\Matching\Handler;

use App\Domains\Matching\CandidateExplainer;
use App\Domains\Matching\Queries\GetTopCandidatesForOpportunity;
use App\Domains\Supervisor\Enums\ProjectEvaluationStatus;
use App\Interfaces\OpportunityRepositoryInterface;
use App\Models\Application;
use App\Models\UserSkill;
use App\Services\Matching\SkillMatchService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GetTopCandidatesForOpportunityHandler
{
    private const SKILL_WEIGHT = 0.55;

    private const PROJECT_WEIGHT = 0.20;

    private const TAG_WEIGHT = 0.10;

    private const ACTIVITY_WEIGHT = 0.10;

    private const FRESHNESS_WEIGHT = 0.05;

    private const FRESHNESS_WINDOW_DAYS = 30;

    public function __construct(
        private readonly OpportunityRepositoryInterface $opportunityRepository,
        private readonly SkillMatchService $skillMatchService,
        private readonly CandidateExplainer $candidateExplainer,
    ) {}

    public static function weights(): array
    {
        return [
            'skills' => 55,
            'projects' => 20,
            'tags' => 10,
            'activity' => 10,
            'freshness' => 5,
        ];
    }

    public function handle(GetTopCandidatesForOpportunity $query): Collection
    {
        $opportunity = $this->opportunityRepository
            ->findCompanyOpportunityOrFail(
                companyId: $query->companyId,
                opportunityId: $query->opportunityId
            );

        $applications = Application::query()
            ->with([
                'user:id,name,email,is_active,profile_picture_url,updated_at',
            ])
            ->where('opportunity_id', $opportunity->id)
            ->where('status', 'pending')
            ->whereHas('user', function ($userQuery): void {
                $userQuery->where('is_active', true);
            })
            ->orderBy('id')
            ->get([
                'id',
                'opportunity_id',
                'user_id',
                'status',
                'applied_at',
            ]);

        if ($applications->isEmpty()) {
            return collect();
        }

        $studentIds = $applications
            ->pluck('user_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $skillsByStudent = $this->getSkillsByStudent($studentIds);
        $projectStats = $this->getProjectStats($studentIds);
        $pointTotals = $this->getPointTotals($studentIds);
        $userTags = $this->getUserTags($studentIds);
        $opportunityTags = $this->getOpportunityTags($opportunity->id);

        $activityRawValues = $studentIds->mapWithKeys(function (int $studentId) use ($pointTotals): array {
            $points = max(
                0,
                (int) ($pointTotals->get($studentId)->total_points ?? 0)
            );

            return [$studentId => log($points + 1)];
        });

        $maxActivityRaw = (float) ($activityRawValues->max() ?? 0.0);

        $ranked = $applications->map(function (Application $application) use (
            $opportunity,
            $skillsByStudent,
            $projectStats,
            $pointTotals,
            $userTags,
            $opportunityTags,
            $activityRawValues,
            $maxActivityRaw
        ): array {
            $studentId = (int) $application->user_id;

            /** @var Collection<int, UserSkill> $studentSkills */
            $studentSkills = $skillsByStudent->get($studentId, collect());

            $skillMatch = $this->skillMatchService->calculate(
                requiredSkills: $opportunity->skills,
                studentSkills: $studentSkills
            );

            $skillScore = $this->clampScore((float) $skillMatch['score']);

            $projectStat = $projectStats->get($studentId);
            $projectScore = $this->clampScore(
                (float) ($projectStat->project_score ?? 0.0)
            );
            $projectCount = (int) ($projectStat->project_count ?? 0);

            $studentTagIds = $userTags->get($studentId, collect());
            $matchedTags = $opportunityTags
                ->filter(
                    fn ($tag): bool => $studentTagIds->contains((int) $tag->tag_id)
                )
                ->values();

            $tagScore = $this->calculateTagScore(
                opportunityTags: $opportunityTags,
                matchedTags: $matchedTags
            );

            $activityPoints = max(
                0,
                (int) ($pointTotals->get($studentId)->total_points ?? 0)
            );
            $activityRaw = (float) $activityRawValues->get($studentId, 0.0);
            $activityScore = $maxActivityRaw > 0
                ? $this->clampScore(($activityRaw / $maxActivityRaw) * 100)
                : 0.0;

            $freshDays = $this->getFreshDays($application);
            $freshnessScore = $this->calculateFreshnessScore($freshDays);

            $finalScore = round(
                ($skillScore * self::SKILL_WEIGHT)
                + ($projectScore * self::PROJECT_WEIGHT)
                + ($tagScore * self::TAG_WEIGHT)
                + ($activityScore * self::ACTIVITY_WEIGHT)
                + ($freshnessScore * self::FRESHNESS_WEIGHT),
                2
            );

            $strongSkills = collect($skillMatch['matched_skills'])
                ->filter(
                    fn (array $skill): bool => ($skill['match_type'] ?? null) === 'full'
                        && (int) ($skill['student_level'] ?? 0) >= 4
                )
                ->pluck('name')
                ->filter()
                ->values()
                ->all();

            $missingSkills = collect($skillMatch['missing_skills'])
                ->pluck('name')
                ->filter()
                ->values()
                ->all();

            $explanation = $this->candidateExplainer->explain([
                'matched_skills' => (int) $skillMatch['matched_skills_count'],
                'partially_matched_skills' => (int) $skillMatch['partially_matched_skills_count'],
                'total_skills' => (int) $skillMatch['total_skills_count'],
                'strong_skills' => $strongSkills,
                'project_count' => $projectCount,
                'project_score' => $projectScore,
                'matched_tags' => $matchedTags->pluck('name')->filter()->values()->all(),
                'total_tags' => $opportunityTags->count(),
                'activity_points' => $activityPoints,
                'activity_score' => $activityScore,
                'fresh_days' => $freshDays,
                'missing_skills' => $missingSkills,
            ]);

            return [
                // Core score keys are normalized to the same 0-100 scale.
                'user_id' => $studentId,
                'skill_score' => $skillScore,
                'project_score' => $projectScore,
                'activity_score' => $activityScore,
                'tag_score' => $tagScore,
                'freshness' => $freshnessScore,
                'final_score' => $finalScore,

                // Applicant context required by the company ranking flow.
                'application_id' => (int) $application->id,
                'application_status' => $application->status,
                'applied_at' => $application->applied_at?->toISOString(),
                'student' => [
                    'id' => $studentId,
                    'name' => $application->user?->name,
                    'email' => $application->user?->email,
                    'profile_picture_url' => $application->user?->profile_picture_url,
                ],
                'scores' => [
                    'skill_score' => $skillScore,
                    'project_score' => $projectScore,
                    'tag_score' => $tagScore,
                    'activity_score' => $activityScore,
                    'freshness_score' => $freshnessScore,
                    'final_score' => $finalScore,
                ],
                'metrics' => [
                    'matched_skills_count' => (int) $skillMatch['matched_skills_count'],
                    'partially_matched_skills_count' => (int) $skillMatch['partially_matched_skills_count'],
                    'total_skills_count' => (int) $skillMatch['total_skills_count'],
                    'missing_mandatory_skills' => array_values($skillMatch['missing_mandatory_skills']),
                    'project_evaluations_count' => $projectCount,
                    'matched_tags_count' => $matchedTags->count(),
                    'total_tags_count' => $opportunityTags->count(),
                    'activity_points' => $activityPoints,
                    'fresh_days' => $freshDays,
                ],
                'explanation' => $explanation,
            ];
        });

        $limit = max(1, min(100, $query->limit));

        return $ranked
            ->sort(function (array $left, array $right): int {
                $scoreComparison = $right['final_score'] <=> $left['final_score'];

                if ($scoreComparison !== 0) {
                    return $scoreComparison;
                }

                return $left['application_id'] <=> $right['application_id'];
            })
            ->take($limit)
            ->values()
            ->map(function (array $candidate, int $index): array {
                return [
                    'rank' => $index + 1,
                    ...$candidate,
                ];
            });
    }

    private function getSkillsByStudent(Collection $studentIds): Collection
    {
        return UserSkill::query()
            ->whereIn('UserId', $studentIds)
            ->get()
            ->groupBy(fn (UserSkill $userSkill): int => (int) $userSkill->UserId)
            ->map(
                fn (Collection $skills): Collection => $skills->keyBy(
                    fn (UserSkill $userSkill): int => (int) $userSkill->SkillId
                )
            );
    }

    private function getProjectStats(Collection $studentIds): Collection
    {
        return DB::table('project_evaluations')
            ->select([
                'student_id',
                DB::raw('AVG(COALESCE(final_grade, total_score)) as project_score'),
                DB::raw('COUNT(*) as project_count'),
            ])
            ->whereIn('student_id', $studentIds)
            ->whereIn('status', [
                ProjectEvaluationStatus::SUBMITTED->value,
                ProjectEvaluationStatus::APPROVED->value,
            ])
            ->groupBy('student_id')
            ->get()
            ->keyBy(fn ($row): int => (int) $row->student_id);
    }

    private function getPointTotals(Collection $studentIds): Collection
    {
        return DB::table('point_transactions')
            ->select([
                'user_id',
                DB::raw('SUM(points) as total_points'),
            ])
            ->whereIn('user_id', $studentIds)
            ->groupBy('user_id')
            ->get()
            ->keyBy(fn ($row): int => (int) $row->user_id);
    }

    private function getUserTags(Collection $studentIds): Collection
    {
        return DB::table('user_tags')
            ->whereIn('user_id', $studentIds)
            ->get(['user_id', 'tag_id'])
            ->groupBy(fn ($row): int => (int) $row->user_id)
            ->map(
                fn (Collection $rows): Collection => $rows
                    ->pluck('tag_id')
                    ->map(fn ($tagId): int => (int) $tagId)
                    ->unique()
                    ->values()
            );
    }

    private function getOpportunityTags(int $opportunityId): Collection
    {
        return DB::table('opportunity_tags')
            ->join('tags', 'tags.id', '=', 'opportunity_tags.tag_id')
            ->where('opportunity_tags.opportunity_id', $opportunityId)
            ->get([
                'opportunity_tags.tag_id',
                'opportunity_tags.weight',
                'opportunity_tags.mandatory',
                'tags.name',
            ]);
    }

    private function calculateTagScore(
        Collection $opportunityTags,
        Collection $matchedTags
    ): float {
        if ($opportunityTags->isEmpty()) {
            return 0.0;
        }

        $totalWeight = (float) $opportunityTags->sum(
            fn ($tag): float => max(0.0, (float) $tag->weight)
        );

        if ($totalWeight <= 0.0) {
            return 0.0;
        }

        $matchedWeight = (float) $matchedTags->sum(
            fn ($tag): float => max(0.0, (float) $tag->weight)
        );

        return $this->clampScore(($matchedWeight / $totalWeight) * 100);
    }

    private function getFreshDays(Application $application): int
    {
        $lastActivity = $application->user?->updated_at;

        if ($lastActivity === null) {
            return 999;
        }

        return (int) floor($lastActivity->diffInDays(now(), true));
    }

    private function calculateFreshnessScore(int $days): float
    {
        if ($days >= self::FRESHNESS_WINDOW_DAYS) {
            return 0.0;
        }

        return $this->clampScore(
            ((self::FRESHNESS_WINDOW_DAYS - $days) / self::FRESHNESS_WINDOW_DAYS) * 100
        );
    }

    private function clampScore(float $score): float
    {
        return round(max(0.0, min(100.0, $score)), 2);
    }
}
