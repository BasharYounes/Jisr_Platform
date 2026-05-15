<?php

namespace App\Interfaces;

use App\Models\CompanyTask;
use Illuminate\Database\Eloquent\Collection;

interface CompanyTaskRepositoryInterface
{
    public function create(array $data): CompanyTask;

    public function update(CompanyTask $task, array $data): CompanyTask;

    public function findCompanyTaskOrFail(int $companyId, int $taskId): CompanyTask;

    public function getByCompany(int $companyId): Collection;

    public function syncSkills(CompanyTask $task, array $skills): void;

    public function publish(CompanyTask $task): CompanyTask;
    public function getExploreTasks(?string $title = null): Collection;

    public function getAvailableTasksWithSkills(): Collection;

    public function findAvailableTaskOrFail(int $taskId): CompanyTask;
}