<?php

namespace App\Interfaces;

use Illuminate\Support\Collection;

interface CompanyHomeRepositoryInterface
{
    public function getCompanySummary(int $companyId): array;

    public function getActiveOpportunitiesStats(int $companyId): array;

    public function getNewApplicantsStats(int $companyId): array;

    public function getPendingReviewsStats(int $companyId): array;

    public function getActiveAssignmentsStats(int $companyId): array;

    public function getRequiredActions(int $companyId): Collection;

    public function getRecentActivities(int $companyId): Collection;
}
