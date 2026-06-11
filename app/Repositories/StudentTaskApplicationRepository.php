<?php

namespace App\Repositories;

use App\Interfaces\StudentTaskApplicationRepositoryInterface;
use App\Models\CompanyTaskApplication;
use App\Models\CompanyTaskAssignment;
use Illuminate\Support\Collection;

class StudentTaskApplicationRepository implements StudentTaskApplicationRepositoryInterface
{
    public function getApplicationsByStatus(int $studentUserId, string $status): Collection
    {
        return CompanyTaskApplication::query()
            ->where('student_user_id', $studentUserId)
            ->where('status', $status)
            ->with([
                'task.company.users:id,name',
                'task.skills:id,name',
            ])
            ->latest('applied_at')
            ->get();
    }

    public function getAcceptedAssignments(int $studentUserId): Collection
    {
        return CompanyTaskAssignment::query()
            ->where('student_user_id', $studentUserId)
            ->with([
                'task.company.users:id,name',
                'task.skills:id,name',
                'application',
            ])
            ->latest('started_at')
            ->get();
    }
}
