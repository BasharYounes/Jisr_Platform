<?php

namespace App\Repositories;

use App\Interfaces\CompanyTaskApplicationRepositoryInterface;
use App\Models\CompanyTaskApplication;
use Illuminate\Database\Eloquent\Collection;

class CompanyTaskApplicationRepository implements CompanyTaskApplicationRepositoryInterface
{
    public function existsForStudent(int $taskId, int $studentUserId): bool
    {
        return CompanyTaskApplication::query()
            ->where('company_task_id', $taskId)
            ->where('student_user_id', $studentUserId)
            ->exists();
    }

    public function create(array $data): CompanyTaskApplication
    {
        return CompanyTaskApplication::create($data);
    }

    public function findStudentApplicationOrFail(
        int $applicationId,
        int $studentUserId
    ): CompanyTaskApplication {
        return CompanyTaskApplication::query()
            ->with(['task.company.users', 'task.skills'])
            ->where('id', $applicationId)
            ->where('student_user_id', $studentUserId)
            ->firstOrFail();
    }
  
    public function getByCompanyTask(int $companyId, int $taskId): Collection
{
    return CompanyTaskApplication::query()
        ->with([
            'student' => function ($query) {
                $query->withCount('portfolioProjects');
            },
        ])
        ->where('company_task_id', $taskId)
        ->whereHas('task', function ($query) use ($companyId) {
            $query->where('company_id', $companyId);
        })
        ->orderByDesc('match_score')
        ->latest('applied_at')
        ->get();
}

public function findCompanyApplicationOrFail(
    int $companyId,
    int $applicationId
): CompanyTaskApplication {
    return CompanyTaskApplication::query()
        ->with([
            'student',
            'task.company.users',
            'task.skills',
        ])
        ->where('id', $applicationId)
        ->whereHas('task', function ($query) use ($companyId) {
            $query->where('company_id', $companyId);
        })
        ->firstOrFail();
}

public function update(
    CompanyTaskApplication $application,
    array $data
): CompanyTaskApplication {
    $application->update($data);

    return $application->fresh([
        'student',
        'task.company.users',
        'task.skills',
    ]);
}

public function countAcceptedForTask(int $taskId): int
{
    return CompanyTaskApplication::query()
        ->where('company_task_id', $taskId)
        ->where('status', 'accepted')
        ->count();
}
    
}