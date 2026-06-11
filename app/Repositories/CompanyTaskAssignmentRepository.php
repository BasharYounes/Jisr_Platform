<?php

namespace App\Repositories;

use App\Interfaces\CompanyTaskAssignmentRepositoryInterface;
use App\Models\CompanyTaskAssignment;

class CompanyTaskAssignmentRepository implements CompanyTaskAssignmentRepositoryInterface
{
    public function existsForApplication(int $applicationId): bool
    {
        return CompanyTaskAssignment::query()
            ->where('company_task_application_id', $applicationId)
            ->exists();
    }

    public function create(array $data): CompanyTaskAssignment
    {
        return CompanyTaskAssignment::create($data)->fresh([
            'task',
            'application',
            'student',
        ]);
    }
}
