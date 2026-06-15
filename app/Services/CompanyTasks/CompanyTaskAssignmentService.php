<?php

namespace App\Services\CompanyTasks;

use App\Interfaces\CompanyTaskAssignmentRepositoryInterface;
use App\Models\CompanyTaskAssignment;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;


class CompanyTaskAssignmentService
{
    public function __construct(
        private readonly CompanyTaskAssignmentRepositoryInterface $assignmentRepository
    ) {}

    public function getCompanyAssignments(int $companyId): Collection
    {
        return $this->assignmentRepository->getByCompany($companyId);
    }

    public function getCompanyAssignmentDetails(
        int $companyId,
        int $assignmentId
    ): CompanyTaskAssignment {
        return $this->assignmentRepository->findCompanyAssignmentDetailsOrFail(
            companyId: $companyId,
            assignmentId: $assignmentId
        );
    }
    
public function closeCompanyAssignment(
    int $companyId,
    int $assignmentId
): CompanyTaskAssignment {
    $assignment = $this->assignmentRepository
        ->findCompanyAssignmentDetailsOrFail(
            companyId: $companyId,
            assignmentId: $assignmentId
        );

    if ($assignment->status !== 'reviewed') {
        throw ValidationException::withMessages([
            'assignment' => [
                'لا يمكن إغلاق التكليف قبل مراجعته. | Assignment cannot be closed before review.',
            ],
        ]);
    }

    if ($assignment->reviews()->doesntExist()) {
        throw ValidationException::withMessages([
            'review' => [
                'لا يمكن إغلاق التكليف بدون تقييم. | Assignment cannot be closed without a review.',
            ],
        ]);
    }

    return $this->assignmentRepository->update(
        $assignment,
        [
            'status' => 'closed',
            'completed_at' => now(),
        ]
    );
}
    
}