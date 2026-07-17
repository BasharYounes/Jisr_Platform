<?php

namespace App\Repositories;

use App\Interfaces\CompanyTaskAssignmentRepositoryInterface;
use App\Models\CompanyTaskAssignment;
use Illuminate\Support\Collection;

class CompanyTaskAssignmentRepository implements CompanyTaskAssignmentRepositoryInterface
{
    public function existsForApplication(int $applicationId): bool
    {
        return CompanyTaskAssignment::query()
            ->where('company_task_application_id', $applicationId)
            ->exists();
    }

    public function create(array $data): CompanyTaskAssignment
    {
        return CompanyTaskAssignment::create($data)->fresh([
            'task',
            'application',
            'student',
        ]);
    }

    public function getByCompany(int $companyId): Collection
    {
        return CompanyTaskAssignment::query()
            ->with([
                'task:id,title,difficulty_level,deadline,status',
                'student:id,name,email,profile_picture_url',
                'application:id,company_task_id,student_user_id,match_score,match_reasons,applied_at',
            ])
            ->whereHas('task', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })
            ->whereIn('status', [
                'not_started',
                'working',
                'submitted',
                'reviewed',
            ])
            ->latest('started_at')
            ->get();
    }

    public function findCompanyAssignmentDetailsOrFail(
        int $companyId,
        int $assignmentId
    ): CompanyTaskAssignment {
        return CompanyTaskAssignment::query()
            ->with([
                'task.skills',
                'student.studentProfile',
                'student.skills',
                'student.portfolioProjects',
                'application',
                'progressUpdates',
                'submissions',
                'reviews',
            ])
            ->where('id', $assignmentId)
            ->whereHas('task', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })
            ->firstOrFail();
    }

    public function update(
        CompanyTaskAssignment $assignment,
        array $data
    ): CompanyTaskAssignment {
        $assignment->update($data);

        return $assignment->refresh()->load([
            'task.skills',
            'student.studentProfile',
            'student.skills',
            'student.portfolioProjects',
            'application',
            'progressUpdates',
            'submissions',
            'reviews',
        ]);
    }
}
