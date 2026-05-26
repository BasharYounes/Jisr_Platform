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
                'active_opportunities_count' => $this->companyHomeRepository->countActiveTasks($companyId),
                'new_applicants_count' => $this->companyHomeRepository->countNewApplicants($companyId),
                'pending_reviews_count' => $this->companyHomeRepository->countPendingReviews($companyId),
                'active_assignments_count' => $this->companyHomeRepository->countActiveAssignments($companyId),
            ],

            'required_actions' => $this->companyHomeRepository->getRequiredActions($companyId),

            'recent_activities' => $this->companyHomeRepository->getRecentActivities($companyId),

        ];
    }
}