<?php

namespace App\Repositories;

use App\Interfaces\CompanyTaskRepositoryInterface;
use App\Models\CompanyTask;
use App\Models\CompanyTaskApplication;
use App\Models\CompanyTaskAssignment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CompanyTaskRepository implements CompanyTaskRepositoryInterface
{
    public function create(array $data): CompanyTask
    {
        return CompanyTask::create($data);
    }

    public function update(CompanyTask $task, array $data): CompanyTask
    {
        $task->update($data);
        $task->fresh(['company', 'skills']);

        return $task;
    }

    public function findCompanyTaskOrFail(
        int $companyId,
        int $taskId
    ): CompanyTask {
        return CompanyTask::query()
            ->with(['company', 'skills'])
            ->withCount([
                'assignments as accepted_students_count' => function ($query) {
                    $query->where('status', '!=', 'cancelled');
                },

                'submissions as submissions_count',
            ])
            ->where('company_id', $companyId)
            ->where('id', $taskId)
            ->firstOrFail();
    }

    public function findTaskForUpdateOrFail(int $taskId): CompanyTask
    {
        return CompanyTask::query()
            ->lockForUpdate()
            ->findOrFail($taskId);
    }

    public function findCompanyTaskForUpdateOrFail(int $companyId, int $taskId): CompanyTask
    {
        return CompanyTask::query()
            ->where('company_id', $companyId)
            ->lockForUpdate()
            ->findOrFail($taskId);
    }

    public function getByCompany(
        int $companyId,
        ?string $status = null
    ): Collection {
        return CompanyTask::query()
            ->with(['skills'])
            ->withCount([
                'assignments as accepted_students_count' => function ($query) {
                    $query->where('status', '!=', 'cancelled');
                },
                'submissions as submissions_count',
            ])
            ->where('company_id', $companyId)
            ->when($status !== null, function ($query) use ($status): void {
                $query->where('status', $status);
            })
            ->latest()
            ->get();
    }

    public function syncSkills(CompanyTask $task, array $skills): void
    {
        $syncData = [];

        foreach ($skills as $skill) {
            $syncData[$skill['skill_id']] = [
                'required_level' => $skill['required_level'] ?? null,
                'weight' => $skill['weight'] ?? 1.00,
                'mandatory' => $skill['mandatory'] ?? true,
            ];
        }

        $task->skills()->sync($syncData);
    }

    public function publish(CompanyTask $task): CompanyTask
    {
        $task->update([
            'status' => 'published',
            'published_at' => now(),
        ]);

        return $task->fresh(['company', 'skills']);
    }

    private function openForApplicationsQuery(): Builder
    {
        return CompanyTask::query()
            ->withCount([
                'applications as applicants_count',
                'assignments as accepted_students_count' => function (Builder $query): void {
                    $query->where('status', '!=', 'cancelled');
                },
            ])
            ->whereIn('status', [
                'published',
                'in_progress',
            ])
            ->where('deadline', '>=', now())
            ->where(function (Builder $query): void {
                $query->whereNull('max_applicants')
                    ->orWhere(
                        CompanyTaskApplication::selectRaw('count(*)')
                            ->whereColumn('company_task_id', 'company_tasks.id'),
                        '<',
                        DB::raw('company_tasks.max_applicants')
                    );
            })
            ->where(function (Builder $query): void {
                $query->whereNull('max_accepted_students')
                    ->orWhere(
                        CompanyTaskAssignment::selectRaw('count(*)')
                            ->whereColumn('company_task_id', 'company_tasks.id')
                            ->where('status', '!=', 'cancelled'),
                        '<',
                        DB::raw('company_tasks.max_accepted_students')
                    );
            });
    }

    public function getExploreTasks(?string $title = null): Collection
    {
        return $this->openForApplicationsQuery()
            ->with(['company', 'skills'])
            ->when($title, function ($query) use ($title): void {
                $query->where('title', 'like', '%'.$title.'%');
            })
            ->latest('published_at')
            ->get();
    }

    public function getAvailableTasksWithSkills(): Collection
    {
        return $this->openForApplicationsQuery()
            ->with(['company', 'skills'])
            ->latest('published_at')
            ->get();
    }

    public function findAvailableTaskOrFail(int $taskId): CompanyTask
    {
        return $this->openForApplicationsQuery()
            ->with(['company', 'skills'])
            ->whereKey($taskId)
            ->firstOrFail();
    }

    public function getUnreviewedAssignmentsForTask(
        CompanyTask $task
    ): Collection {
        return $task->assignments()
            ->where('status', '!=', 'cancelled')
            ->where(function ($query): void {
                $query
                    ->where('status', '!=', 'reviewed')
                    ->orWhereDoesntHave('reviews');
            })
            ->with([
                'task.skills',
                'application',
                'student.studentProfile',
                'student.skills',
                'student.portfolioProjects',
                'progressUpdates',
                'submissions',
                'reviews',
            ])
            ->get();
    }

    public function close(
        CompanyTask $task
    ): CompanyTask {
        $task->update([
            'status' => 'closed',
        ]);

        return $task->fresh([
            'company',
            'skills',
        ]);
    }

    public function findCompanyTaskWithAssignmentsOrFail(
        int $companyId,
        int $taskId
    ): CompanyTask {
        return CompanyTask::query()
            ->where('company_id', $companyId)
            ->whereKey($taskId)
            ->with([
                'company',
                'skills',
                'applications',
                'assignments.task.skills',
                'assignments.application',
                'assignments.student.studentProfile',
                'assignments.student.skills',
                'assignments.student.portfolioProjects',
                'assignments.progressUpdates',
                'assignments.submissions',
                'assignments.reviews',
            ])
            ->firstOrFail();
    }

    public function getCancellationBlockingAssignmentsForTask(
        CompanyTask $task
    ): Collection {
        return $task->assignments()
            ->whereIn('status', [
                'working',
                'submitted',
                'reviewed',
            ])
            ->with([
                'task.skills',
                'application',
                'student.studentProfile',
                'student.skills',
                'student.portfolioProjects',
                'progressUpdates',
                'submissions',
                'reviews',
            ])
            ->get();
    }

    public function rejectPendingApplicationsForCancelledTask(
        CompanyTask $task,
        ?string $reason = null
    ): int {
        return $task->applications()
            ->where('status', 'pending')
            ->update([
                'status' => 'rejected',
                'reviewed_at' => now(),
                'company_notes' => $reason
                    ?: 'تم رفض الطلب بسبب إلغاء التاسك من قبل الشركة. | Application rejected because the task was cancelled by the company.',
                'updated_at' => now(),
            ]);
    }

    public function cancel(
        CompanyTask $task
    ): CompanyTask {
        $task->update([
            'status' => 'cancelled',
        ]);

        return $task->fresh([
            'company',
            'skills',
        ]);
    }
}
