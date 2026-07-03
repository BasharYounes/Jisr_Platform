<?php

namespace App\Services\Opportunities;

use App\Interfaces\OpportunityApplicationRepositoryInterface;
use App\Interfaces\OpportunityRepositoryInterface;
use App\Models\Opportunity;
use Illuminate\Support\Collection;

class StudentOpportunityService
{
    public function __construct(
        private readonly OpportunityRepositoryInterface $opportunityRepository,
        private readonly OpportunityApplicationRepositoryInterface $applicationRepository,
        private readonly OpportunityRecommendationService $recommendationService,
    ) {}

    public function getRecommended(int $studentUserId): Collection
    {
        return $this->recommendationService
            ->getRecommendedForStudent($studentUserId);
    }

    public function getExplore(int $studentUserId): Collection
    {
        return $this->recommendationService
            ->getExploreForStudent($studentUserId);
    }

    public function show(
        int $studentUserId,
        int $opportunityId
    ): Opportunity {
        $opportunity = $this->opportunityRepository
            ->findPublishedActiveOrFail($opportunityId);

        $application = $this->applicationRepository
            ->findForStudentAndOpportunity(
                studentUserId: $studentUserId,
                opportunityId: $opportunity->id
            );

        $matchData = $this->recommendationService->calculateMatch(
            opportunity: $opportunity,
            studentUserId: $studentUserId
        );

        $opportunity->match_data = $matchData;
        $opportunity->already_applied = $application !== null;
        $opportunity->application_status = $application?->status;

        $opportunity->can_apply = $application === null;
        $opportunity->cannot_apply_reason = $application !== null
            ? 'لقد قدمت مسبقًا على هذه الفرصة. | You have already applied to this opportunity.'
            : null;

        return $opportunity;
    }
}
