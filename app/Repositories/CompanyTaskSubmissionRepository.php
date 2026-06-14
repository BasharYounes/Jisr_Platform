<?php

namespace App\Repositories;

use App\Interfaces\CompanyTaskSubmissionRepositoryInterface;
use App\Models\CompanyTaskAssignment;
use App\Models\CompanyTaskSubmission;

class CompanyTaskSubmissionRepository implements CompanyTaskSubmissionRepositoryInterface
{
    public function findStudentAssignmentOrFail(
        int $assignmentId,
        int $studentUserId
    ): CompanyTaskAssignment {
        return CompanyTaskAssignment::query()
            ->whereKey($assignmentId)
            ->where('student_user_id', $studentUserId)
            ->with([
                'task:id,company_id,title,deadline,submission_type',
                'student:id,name,email,profile_picture_url',
            ])
            ->firstOrFail();
    }

    public function findCompanyAssignmentOrFail(
        int $assignmentId,
        int $companyId
    ): CompanyTaskAssignment {
        return CompanyTaskAssignment::query()
            ->whereKey($assignmentId)
            ->whereHas('task', function ($query) use ($companyId): void {
                $query->where('company_id', $companyId);
            })
            ->with([
                'task:id,company_id,title,deadline,submission_type',
                'student:id,name,email,profile_picture_url',
            ])
            ->firstOrFail();
    }

    public function findLatestByAssignment(
        int $assignmentId
    ): ?CompanyTaskSubmission {
        return CompanyTaskSubmission::query()
            ->where('company_task_assignment_id', $assignmentId)
            ->with([
                'assignment.task' => function ($query) {
                    $query->withCount([
                        'applications as accepted_students_count' => function ($query) {
                            $query->where('status', 'accepted');
                        },
                        'submissions',
                    ]);
                },
                'student:id,name,email,profile_picture_url',
            ])
            ->latest('submitted_at')
            ->latest('id')
            ->first();
    }

    public function create(array $data): CompanyTaskSubmission
    {
        return CompanyTaskSubmission::query()->create($data);
    }

    public function markAssignmentAsSubmitted(
        CompanyTaskAssignment $assignment
    ): CompanyTaskAssignment {
        $assignment->update([
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        return $assignment->refresh();
    }
}
