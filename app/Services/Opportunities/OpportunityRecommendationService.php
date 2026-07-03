<?php

namespace App\Services\Opportunities;

use App\Interfaces\OpportunityApplicationRepositoryInterface;
use App\Interfaces\OpportunityRepositoryInterface;
use App\Models\Opportunity;
use App\Models\UserSkill;
use App\Services\Matching\SkillMatchService;
use Illuminate\Support\Collection;

class OpportunityRecommendationService
{
    private const RECOMMENDED_MIN_SCORE = 50.0;

    private const EXPLORE_MIN_SCORE = 35.0;

    public function __construct(
        private readonly OpportunityRepositoryInterface $opportunityRepository,
        private readonly OpportunityApplicationRepositoryInterface $applicationRepository,
        private readonly SkillMatchService $skillMatchService,
    ) {}

    public function getRecommendedForStudent(int $studentUserId): Collection
    {
        return $this->getOpportunitiesWithMatch($studentUserId)
            ->filter(function (Opportunity $opportunity): bool {
                $match = $opportunity->match_data;

                return $match['score'] >= self::RECOMMENDED_MIN_SCORE
                    && $match['is_eligible_for_recommendation'] === true;
            })
            ->sortByDesc(fn (Opportunity $opportunity): float => $opportunity->match_data['score'])
            ->values();
    }

    public function getExploreForStudent(int $studentUserId): Collection
    {
        return $this->getOpportunitiesWithMatch($studentUserId)
            ->filter(function (Opportunity $opportunity): bool {
                $match = $opportunity->match_data;

                return $match['score'] >= self::EXPLORE_MIN_SCORE
                    && (
                        $match['score'] < self::RECOMMENDED_MIN_SCORE
                        || $match['is_eligible_for_recommendation'] === false
                    );
            })
            ->sortByDesc(fn (Opportunity $opportunity): float => $opportunity->match_data['score'])
            ->values();
    }

    public function calculateMatch(
        Opportunity $opportunity,
        int $studentUserId
    ): array {
        $studentSkills = $this->getStudentSkills($studentUserId);

        return $this->skillMatchService->calculate(
            requiredSkills: $opportunity->skills,
            studentSkills: $studentSkills
        );
    }

    private function getOpportunitiesWithMatch(int $studentUserId): Collection
    {
        return $this->opportunityRepository
            ->getPublishedActiveOpportunities()
            ->reject(function (Opportunity $opportunity) use ($studentUserId): bool {
                return $this->applicationRepository->existsForStudent(
                    studentUserId: $studentUserId,
                    opportunityId: $opportunity->id
                );
            })
            ->map(function (Opportunity $opportunity) use ($studentUserId): Opportunity {
                $opportunity->match_data = $this->calculateMatch(
                    opportunity: $opportunity,
                    studentUserId: $studentUserId
                );

                $opportunity->already_applied = false;
                $opportunity->application_status = null;

                return $opportunity;
            });
    }

    private function getStudentSkills(int $studentUserId): Collection
    {
        return UserSkill::query()
            ->where('UserId', $studentUserId)
            ->get()
            ->keyBy('SkillId');
    }
}
