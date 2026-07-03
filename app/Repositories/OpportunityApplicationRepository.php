<?php

namespace App\Repositories;

use App\Interfaces\OpportunityApplicationRepositoryInterface;
use App\Models\Application;

class OpportunityApplicationRepository implements OpportunityApplicationRepositoryInterface
{
    public function existsForStudent(
        int $studentUserId,
        int $opportunityId
    ): bool {
        return Application::query()
            ->where('user_id', $studentUserId)
            ->where('opportunity_id', $opportunityId)
            ->exists();
    }

    public function findForStudentAndOpportunity(
        int $studentUserId,
        int $opportunityId
    ): ?Application {
        return Application::query()
            ->with([
                'cv',
                'interview',
            ])
            ->where('user_id', $studentUserId)
            ->where('opportunity_id', $opportunityId)
            ->first();
    }
}
