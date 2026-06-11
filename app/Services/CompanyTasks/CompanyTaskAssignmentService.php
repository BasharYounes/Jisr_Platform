<?php

namespace App\Services\CompanyTasks;

use App\Interfaces\CompanyTaskAssignmentRepositoryInterface;
use App\Models\CompanyTaskAssignment;
use Illuminate\Support\Collection;

class CompanyTaskAssignmentService
{
    public function __construct(
        private readonly CompanyTaskAssignmentRepositoryInterface $assignmentRepository
    ) {}

    public function getCompanyAssignments(int $companyId): Collection
    {
        return $this->assignmentRepository->getByCompany($companyId);
    }

    public function getCompanyAssignmentDetails(
        int $companyId,
        int $assignmentId
    ): CompanyTaskAssignment {
        return $this->assignmentRepository->findCompanyAssignmentDetailsOrFail(
            companyId: $companyId,
            assignmentId: $assignmentId
        );
    }
}