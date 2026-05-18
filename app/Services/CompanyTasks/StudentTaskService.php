<?php

namespace App\Services\CompanyTasks;

use App\Interfaces\CompanyTaskRepositoryInterface;
use App\Models\CompanyTask;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use App\Interfaces\CompanyTaskApplicationRepositoryInterface;
use App\Models\CompanyTaskApplication;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentTaskService
{
    public function __construct(
        private readonly CompanyTaskRepositoryInterface $companyTaskRepository,
        private readonly TaskRecommendationService $taskRecommendationService,
        private readonly CompanyTaskApplicationRepositoryInterface $companyTaskApplicationRepository

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

    public function applyToTask(
    int $studentUserId,
    int $taskId,
    array $data
): CompanyTaskApplication {
    return DB::transaction(function () use ($studentUserId, $taskId, $data) {
        $task = $this->companyTaskRepository->findAvailableTaskOrFail($taskId);

        if ($this->companyTaskApplicationRepository->existsForStudent($task->id, $studentUserId)) {
            throw ValidationException::withMessages([
                'task' => [
                    'لقد قمت بالتقديم على هذه المهمة مسبقاً. | You have already applied to this task.',
                ],
            ]);
        }

        return $this->companyTaskApplicationRepository->create([
            'company_task_id' => $task->id,
            'student_user_id' => $studentUserId,
            'message' => $data['message'] ?? null,
            'portfolio_url' => $data['portfolio_url'] ?? null,
            'github_url' => $data['github_url'] ?? null,
            'status' => 'pending',
            'applied_at' => now(),
        ])->fresh(['task.company.users', 'task.skills']);
    });
}

}