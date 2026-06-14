<?php

namespace App\Interfaces;

use App\Models\CompanyTaskAssignment;
use App\Models\CompanyTaskReview;
use App\Models\CompanyTaskSubmission;

interface CompanyTaskReviewRepositoryInterface
{
    public function findCompanySubmissionOrFail(
        int $submissionId,
        int $companyId
    ): CompanyTaskSubmission;

    public function findBySubmission(
        int $submissionId
    ): ?CompanyTaskReview;

    public function findCompanyReviewOrFail(
        int $submissionId,
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
