<?php

namespace App\Services\CompanyTasks;

use App\Interfaces\CompanyTaskRepositoryInterface;
use App\Models\CompanyTask;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompanyTaskService
{
    public function __construct(
        private readonly CompanyTaskRepositoryInterface $companyTaskRepository
    ) {}

    public function createTask(int $companyId, array $data): CompanyTask
    {
        return DB::transaction(function () use ($companyId, $data) {
            $skills = $data['skills'] ?? [];

            unset($data['skills']);

            $task = $this->companyTaskRepository->create([
                ...$data,
                'company_id' => $companyId,
                'status' => 'draft',
            ]);

            $this->companyTaskRepository->syncSkills($task, $skills);

            return $task->fresh(['company', 'skills']);
        });
    }

    public function updateTask(int $companyId, int $taskId, array $data): CompanyTask
    {
        return DB::transaction(function () use ($companyId, $taskId, $data) {
            $task = $this->companyTaskRepository->findCompanyTaskOrFail($companyId, $taskId);

            $this->ensureTaskCanBeUpdated($task);

            $skills = $data['skills'] ?? null;

            unset($data['skills']);

            $updatedTask = $this->companyTaskRepository->update($task, $data);

            if ($skills !== null) {
                $this->companyTaskRepository->syncSkills($updatedTask, $skills);
            }

            return $updatedTask->fresh(['company', 'skills']);
        });
    }

    public function getCompanyTasks(int $companyId)
    {
        return $this->companyTaskRepository->getByCompany($companyId);
    }

    public function getCompanyTaskDetails(int $companyId, int $taskId): CompanyTask
    {
        return $this->companyTaskRepository->findCompanyTaskOrFail($companyId, $taskId);
    }

    public function publishTask(int $companyId, int $taskId): CompanyTask
    {
        return DB::transaction(function () use ($companyId, $taskId) {
            $task = $this->companyTaskRepository->findCompanyTaskOrFail($companyId, $taskId);

            $this->ensureTaskCanBePublished($task);

            return $this->companyTaskRepository->publish($task);
        });
    }

    private function ensureTaskCanBeUpdated(CompanyTask $task): void
    {
        if (in_array($task->status, ['completed', 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'task' => ['Completed or cancelled tasks cannot be updated.'],
            ]);
        }
    }

    private function ensureTaskCanBePublished(CompanyTask $task): void
    {
        if ($task->status !== 'draft') {
            throw ValidationException::withMessages([
                'task' => ['Only draft tasks can be published.'],
            ]);
        }

        if ($task->skills()->count() === 0) {
            throw ValidationException::withMessages([
                'skills' => ['At least one required skill is needed before publishing the task.'],
            ]);
        }

        if ($task->deadline->isPast()) {
            throw ValidationException::withMessages([
                'deadline' => ['Task deadline must be in the future before publishing.'],
            ]);
        }
    }
}