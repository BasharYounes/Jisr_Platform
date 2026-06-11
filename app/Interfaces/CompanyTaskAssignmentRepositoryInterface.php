<?php

namespace App\Interfaces;

use App\Models\CompanyTaskAssignment;

interface CompanyTaskAssignmentRepositoryInterface
{
    public function existsForApplication(int $applicationId): bool;

    public function create(array $data): CompanyTaskAssignment;

    public function getByCompany(int $companyId): Collection;

public function findCompanyAssignmentDetailsOrFail(
    int $companyId,
    int $assignmentId
): CompanyTaskAssignment;
}