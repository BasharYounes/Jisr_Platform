<?php

namespace App\Interfaces;

use App\Models\Application;
use Illuminate\Support\Collection;

interface OpportunityApplicationRepositoryInterface
{
    public function existsForStudent(
        int $studentUserId,
        int $opportunityId
    ): bool;

    public function findForStudentAndOpportunity(
        int $studentUserId,
        int $opportunityId
    ): ?Application;

    public function create(array $data): Application;

    public function getStudentApplications(int $studentUserId): Collection;

    public function findStudentApplicationOrFail(
        int $studentUserId,
        int $applicationId
    ): Application;

    public function update(Application $application, array $data): Application;
}
