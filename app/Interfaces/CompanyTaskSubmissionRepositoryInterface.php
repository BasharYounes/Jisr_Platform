<?php

namespace App\Interfaces;

use App\Models\CompanyTaskAssignment;
use App\Models\CompanyTaskSubmission;

interface CompanyTaskSubmissionRepositoryInterface
{
    public function findStudentAssignmentOrFail(
        int $assignmentId,
        int $studentUserId
    ): CompanyTaskAssignment;

    public function findCompanyAssignmentOrFail(
        int $assignmentId,
        int $companyId
    ): CompanyTaskAssignment;

    public function findLatestByAssignment(
        int $assignmentId
    ): ?CompanyTaskSubmission;

    public function create(array $data): CompanyTaskSubmission;

    public function markAssignmentAsSubmitted(
        CompanyTaskAssignment $assignment
    ): CompanyTaskAssignment;
}
