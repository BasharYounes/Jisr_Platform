<?php

namespace App\Repositories;

use App\Interfaces\CompanyTaskProgressRepositoryInterface;
use App\Models\CompanyTaskAssignment;
use App\Models\CompanyTaskProgressUpdate;
use Illuminate\Support\Collection;

class CompanyTaskProgressRepository implements CompanyTaskProgressRepositoryInterface
{
    public function findStudentAssignmentOrFail(
        int $assignmentId,
        int $studentUserId
    ): CompanyTaskAssignment {
        return CompanyTaskAssignment::query()
            ->whereKey($assignmentId)
            ->where('student_user_id', $studentUserId)
            ->with([
                'task:id,company_id,title,deadline',
            ])
            ->firstOrFail();
    }

    public function findCompanyAssignmentOrFail(
        int $assignmentId,
        int $companyId
    ): CompanyTaskAssignment {
        return CompanyTaskAssignment::query()
            ->whereKey($assignmentId)
            ->whereHas('task', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })
            ->with([
                'task:id,company_id,title,deadline',
                'student:id,name,email',
            ])
            ->firstOrFail();
    }

    public function getAssignmentProgressUpdates(
        int $assignmentId
    ): Collection {
        return CompanyTaskProgressUpdate::query()
            ->where('company_task_assignment_id', $assignmentId)
            ->with([
                'student:id,name,email',
            ])
            ->latest()
            ->get();
    }

    public function getLatestProgressPercentage(
        int $assignmentId
    ): int {
        return (int) CompanyTaskProgressUpdate::query()
            ->where('company_task_assignment_id', $assignmentId)
            ->latest('created_at')
            ->value('progress_percentage');
    }

    public function create(array $data): CompanyTaskProgressUpdate
    {
        return CompanyTaskProgressUpdate::query()->create($data);
    }
}