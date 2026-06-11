<?php

namespace App\Interfaces;

use App\Models\CompanyTaskAssignment;
use App\Models\CompanyTaskProgressUpdate;
use Illuminate\Support\Collection;

interface CompanyTaskProgressRepositoryInterface
{
    public function findStudentAssignmentOrFail(
        int $assignmentId,
        int $studentUserId
    ): CompanyTaskAssignment;

    public function findCompanyAssignmentOrFail(
        int $assignmentId,
        int $companyId
    ): CompanyTaskAssignment;

    public function getAssignmentProgressUpdates(
        int $assignmentId
    ): Collection;

    public function getLatestProgressPercentage(
        int $assignmentId
    ): int;

    public function create(
        array $data
    ): CompanyTaskProgressUpdate;
}
