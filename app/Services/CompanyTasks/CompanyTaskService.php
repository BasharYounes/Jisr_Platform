<?php

namespace App\Services\CompanyTasks;

use App\Interfaces\CompanyTaskRepositoryInterface;
use App\Models\CompanyTask;
use Illuminate\Support\Collection;
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

    public function getCompanyTasks(
        int $companyId,
        ?string $status = null
    ) {
        return $this->companyTaskRepository->getByCompany(
            companyId: $companyId,
            status: $status
        );
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

    public function getTaskCloseBlockingAssignments(
        int $companyId,
        int $taskId
    ): Collection {
        $task = $this->companyTaskRepository
            ->findCompanyTaskWithAssignmentsOrFail(
                companyId: $companyId,
                taskId: $taskId
            );

        return $this->companyTaskRepository
            ->getUnreviewedAssignmentsForTask($task);
    }

    public function closeTask(
        int $companyId,
        int $taskId
    ): CompanyTask {
        return DB::transaction(function () use ($companyId, $taskId): CompanyTask {
            $task = $this->companyTaskRepository
                ->findCompanyTaskWithAssignmentsOrFail(
                    companyId: $companyId,
                    taskId: $taskId
                );

            if ($task->status === 'closed') {
                throw ValidationException::withMessages([
                    'task' => [
                        'هذا التاسك مغلق مسبقًا. | This task is already closed.',
                    ],
                ]);
            }

            $blockingAssignments = $this->companyTaskRepository
                ->getUnreviewedAssignmentsForTask($task);

            if ($blockingAssignments->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'assignments' => [
                        'لا يمكن إغلاق التاسك قبل تقييم كل الطلاب المرتبطين به. | Cannot close the task before reviewing all assigned students.',
                    ],
                ]);
            }

            return $this->companyTaskRepository->close($task);
        });
    }

    public function getTaskCancellationBlockingAssignments(
        int $companyId,
        int $taskId
    ): Collection {
        $task = $this->companyTaskRepository
            ->findCompanyTaskWithAssignmentsOrFail(
                companyId: $companyId,
                taskId: $taskId
            );

        return $this->companyTaskRepository
            ->getCancellationBlockingAssignmentsForTask($task);
    }

    public function cancelTask(
        int $companyId,
        int $taskId,
        ?string $reason = null
    ): CompanyTask {
        return DB::transaction(function () use (
            $companyId,
            $taskId,
            $reason
        ): CompanyTask {
            $task = $this->companyTaskRepository
                ->findCompanyTaskWithAssignmentsOrFail(
                    companyId: $companyId,
                    taskId: $taskId
                );

            $this->ensureTaskCanBeCancelled($task);

            $blockingAssignments = $this->companyTaskRepository
                ->getCancellationBlockingAssignmentsForTask($task);

            if ($blockingAssignments->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'assignments' => [
                        'لا يمكن إلغاء التاسك لوجود طلاب تم قبولهم عليه. | Cannot cancel the task because there are accepted students assigned to it.',
                    ],
                ]);
            }

            $this->companyTaskRepository
                ->rejectPendingApplicationsForCancelledTask(
                    task: $task,
                    reason: $reason
                );

            return $this->companyTaskRepository->cancel($task);
        });
    }

    private function ensureTaskCanBeCancelled(
        CompanyTask $task
    ): void {
        if ($task->status === 'cancelled') {
            throw ValidationException::withMessages([
                'task' => [
                    'هذا التاسك ملغى مسبقًا. | This task is already cancelled.',
                ],
            ]);
        }

        if ($task->status === 'closed') {
            throw ValidationException::withMessages([
                'task' => [
                    'لا يمكن إلغاء تاسك مغلق. | A closed task cannot be cancelled.',
                ],
            ]);
        }

        if (! in_array($task->status, ['draft', 'published'], true)) {
            throw ValidationException::withMessages([
                'task' => [
                    'يمكن إلغاء التاسك فقط قبل بدء التنفيذ. | A task can only be cancelled before execution starts.',
                ],
            ]);
        }
    }
}
