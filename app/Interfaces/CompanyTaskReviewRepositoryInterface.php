<?php

namespace App\Interfaces;

use App\Models\CompanyTaskAssignment;
use App\Models\CompanyTaskReview;
use App\Models\CompanyTaskSubmission;

interface CompanyTaskReviewRepositoryInterface
{
    public function findCompanyAssignmentOrFail(
        int $assignmentId,
        int $companyId
    ): CompanyTaskAssignment;

    public function findLatestSubmittedSubmissionForAssignment(
        int $assignmentId
    ): ?CompanyTaskSubmission;

    public function findBySubmission(
        int $submissionId
    ): ?CompanyTaskReview;

    public function findLatestCompanyReviewByAssignmentOrFail(
        int $assignmentId,
        int $companyId
    ): CompanyTaskReview;

    public function create(array $data): CompanyTaskReview;

    public function updateSubmission(
        CompanyTaskSubmission $submission,
        array $data
    ): CompanyTaskSubmission;

    public function updateAssignment(
        CompanyTaskAssignment $assignment,
        array $data
    ): CompanyTaskAssignment;
}
