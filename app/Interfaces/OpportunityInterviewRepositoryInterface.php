<?php

namespace App\Interfaces;

use App\Models\OpportunityInterview;
use Illuminate\Support\Collection;

interface OpportunityInterviewRepositoryInterface
{
    public function create(array $data): OpportunityInterview;

    public function findByApplicationId(int $applicationId): ?OpportunityInterview;

    public function findCompanyInterviewOrFail(
        int $companyId,
        int $opportunityId,
        int $interviewId
    ): OpportunityInterview;

    public function update(
        OpportunityInterview $interview,
        array $data
    ): OpportunityInterview;

    public function getStudentInterviews(
        int $studentUserId,
        array $filters = []
    ): Collection;
}
