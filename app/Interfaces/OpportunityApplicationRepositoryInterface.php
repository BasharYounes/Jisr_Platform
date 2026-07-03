<?php

namespace App\Interfaces;

use App\Models\Application;

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
}
