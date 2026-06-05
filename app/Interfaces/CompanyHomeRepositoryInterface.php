<?php

namespace App\Interfaces;

use Illuminate\Support\Collection;

interface CompanyHomeRepositoryInterface
{
    public function getCompanySummary(int $companyId): array;

    public function countActiveTasks(int $companyId): int;

    public function countNewApplicants(int $companyId): int;

    public function countPendingReviews(int $companyId): int;

    public function countActiveAssignments(int $companyId): int;

    public function getRequiredActions(int $companyId): Collection;

    public function getRecentActivities(int $companyId): Collection;
}