<?php

namespace App\Interfaces;

use App\Models\CompanyTaskAssignment;

interface CompanyTaskAssignmentRepositoryInterface
{
    public function existsForApplication(int $applicationId): bool;

    public function create(array $data): CompanyTaskAssignment;
}