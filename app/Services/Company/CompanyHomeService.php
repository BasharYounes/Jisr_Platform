<?php

namespace App\Services\Company;

use App\Interfaces\CompanyHomeRepositoryInterface;

class CompanyHomeService
{
    public function __construct(
        private readonly CompanyHomeRepositoryInterface $companyHomeRepository
    ) {}

    public function getHomeData(int $companyId): array
    {
        return [
            'company' => $this->companyHomeRepository->getCompanySummary($companyId),

            'stats' => [
                'active_opportunities' => $this->companyHomeRepository->getActiveOpportunitiesStats($companyId),
                'new_applicants' => $this->companyHomeRepository->getNewApplicantsStats($companyId),
                'active_assignments' => $this->companyHomeRepository->getActiveAssignmentsStats($companyId),
                'pending_reviews' => $this->companyHomeRepository->getPendingReviewsStats($companyId),
            ],

            'required_actions' => $this->companyHomeRepository->getRequiredActions($companyId),

            'recent_activities' => $this->companyHomeRepository->getRecentActivities($companyId),

        ];
    }
}
