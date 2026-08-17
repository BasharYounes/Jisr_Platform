<?php

namespace App\Services\Opportunities;

use App\Interfaces\OpportunityInterviewRepositoryInterface;
use Illuminate\Support\Collection;

class StudentOpportunityInterviewService
{
    public function __construct(
        private readonly OpportunityInterviewRepositoryInterface $interviewRepository
    ) {}

    public function getInterviews(
        int $studentUserId,
        array $filters = []
    ): Collection {
        return $this->interviewRepository->getStudentInterviews(
            studentUserId: $studentUserId,
            filters: $filters
        );
    }
}
