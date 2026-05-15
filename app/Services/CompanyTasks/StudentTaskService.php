<?php

namespace App\Services\CompanyTasks;

use App\Interfaces\CompanyTaskRepositoryInterface;
use App\Models\CompanyTask;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class StudentTaskService
{
    public function __construct(
        private readonly CompanyTaskRepositoryInterface $companyTaskRepository,
        private readonly TaskRecommendationService $taskRecommendationService
    ) {}

    public function getExploreTasks(?string $title = null): EloquentCollection
    {
        return $this->companyTaskRepository->getExploreTasks($title);
    }

    public function getRecommendedTasks(int $studentUserId): Collection
    {
        $tasks = $this->companyTaskRepository->getAvailableTasksWithSkills();

        return $this->taskRecommendationService->rankTasksForStudent(
            studentUserId: $studentUserId,
            tasks: $tasks
        );
    }

    public function getTaskDetails(int $taskId): CompanyTask
    {
        return $this->companyTaskRepository->findAvailableTaskOrFail($taskId);
    }
}