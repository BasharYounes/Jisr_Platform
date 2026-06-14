<?php

namespace App\Repositories;

use App\Interfaces\CompanyTaskReviewRepositoryInterface;
use App\Models\CompanyTaskAssignment;
use App\Models\CompanyTaskReview;
use App\Models\CompanyTaskSubmission;

class CompanyTaskReviewRepository implements CompanyTaskReviewRepositoryInterface
{
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
                'task:id,company_id,title,deadline',
                'student:id,name,email,profile_picture_url',
                'submissions' => function ($query): void {
                    $query->latest('id');
                },
                'reviews' => function ($query): void {
                    $query->latest('id');
                },
            ])
            ->firstOrFail();
    }

    public function findLatestSubmittedSubmissionForAssignment(
        int $assignmentId
    ): ?CompanyTaskSubmission {
        return CompanyTaskSubmission::query()
            ->where('company_task_assignment_id', $assignmentId)
            ->where('status', 'submitted')
            ->with([
                'assignment.task:id,company_id,title,deadline',
                'student:id,name,email,profile_picture_url',
                'review',
            ])
            ->latest('id')
            ->first();
    }

    public function findBySubmission(
        int $submissionId
    ): ?CompanyTaskReview {
        return CompanyTaskReview::query()
            ->where('company_task_submission_id', $submissionId)
            ->first();
    }

    public function findLatestCompanyReviewByAssignmentOrFail(
        int $assignmentId,
        int $companyId
    ): CompanyTaskReview {
        return CompanyTaskReview::query()
            ->where('company_task_assignment_id', $assignmentId)
            ->where('company_id', $companyId)
            ->with([
                'submission',
                'assignment.task:id,company_id,title,deadline',
                'student:id,name,email,profile_picture_url',
                'company.users:id,name',
            ])
            ->latest('id')
            ->firstOrFail();
    }

    public function create(array $data): CompanyTaskReview
    {
        return CompanyTaskReview::query()->create($data);
    }

    public function updateSubmission(
        CompanyTaskSubmission $submission,
        array $data
    ): CompanyTaskSubmission {
        $submission->update($data);

        return $submission->refresh();
    }

    public function updateAssignment(
        CompanyTaskAssignment $assignment,
        array $data
    ): CompanyTaskAssignment {
        $assignment->update($data);

        return $assignment->refresh();
    }
}
