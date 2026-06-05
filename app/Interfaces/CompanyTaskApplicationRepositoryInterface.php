<?php

namespace App\Interfaces;

use App\Models\CompanyTaskApplication;
use Illuminate\Database\Eloquent\Collection;
interface CompanyTaskApplicationRepositoryInterface
{
    public function existsForStudent(int $taskId, int $studentUserId): bool;

    public function create(array $data): CompanyTaskApplication;

    public function findStudentApplicationOrFail(int $applicationId,int $studentUserId): CompanyTaskApplication;
    public function getByCompanyTask(int $companyId, int $taskId): Collection;

    public function findCompanyApplicationOrFail(int $companyId,int $applicationId): CompanyTaskApplication;

    public function update(
    CompanyTaskApplication $application,
    array $data
    ): CompanyTaskApplication;

    public function countAcceptedForTask(int $taskId): int;
    public function findCompanyApplicantDetailsOrFail(
    int $companyId,
    int $applicationId
): CompanyTaskApplication;

    

    }