<?php

namespace App\Repositories;

use App\Interfaces\CompanyTaskReviewRepositoryInterface;
use App\Models\CompanyTaskAssignment;
use App\Models\CompanyTaskReview;
use App\Models\CompanyTaskSubmission;

class CompanyTaskReviewRepository implements CompanyTaskReviewRepositoryInterface
{
    public function findCompanySubmissionOrFail(
        int $submissionId,
        int $companyId
    ): CompanyTaskSubmission {
        return CompanyTaskSubmission::query()
            ->whereKey($submissionId)
            ->whereHas('assignment.task', function ($query) use ($companyId): void {
                $query->where('company_id', $companyId);
            })
            ->with([
                'assignment.task:id,company_id,title,deadline',
                'student:id,name,email,profile_picture_url',
                'review',
            ])
            ->firstOrFail();
    }

    public function findBySubmission(
        int $submissionId
    ): ?CompanyTaskReview {
        return CompanyTaskReview::query()
            ->where('company_task_submission_id', $submissionId)
            ->first();
    }

    public function findCompanyReviewOrFail(
        int $submissionId,
        int $companyId
    ): CompanyTaskReview {
        return CompanyTaskReview::query()
            ->where('company_task_submission_id', $submissionId)
            ->where('company_id', $companyId)
            ->with([
                'submission',
                'assignment.task:id,company_id,title,deadline',
                'student:id,name,email,profile_picture_url',
                'company.users:id,name',
            ])
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
