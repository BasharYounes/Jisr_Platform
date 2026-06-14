<?php

namespace App\Services\CompanyTasks;

use App\Interfaces\CompanyTaskReviewRepositoryInterface;
use App\Models\CompanyTaskReview;
use App\Models\CompanyTaskSubmission;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompanyTaskReviewService
{
    public function __construct(
        private readonly CompanyTaskReviewRepositoryInterface $reviewRepository
    ) {}

    public function createReview(
        int $submissionId,
        int $companyId,
        array $data
    ): CompanyTaskReview {
        $submission = $this->reviewRepository
            ->findCompanySubmissionOrFail(
                submissionId: $submissionId,
                companyId: $companyId
            );

        $this->ensureSubmissionCanBeReviewed($submission);
        $this->ensureReviewDoesNotExist($submissionId);

        $totalScore = $this->calculateTotalScore(
            qualityScore: (int) $data['quality_score'],
            commitmentScore: (int) $data['commitment_score'],
            communicationScore: (int) $data['communication_score']
        );

        $review = DB::transaction(function () use (
            $submission,
            $companyId,
            $data,
            $totalScore
        ): CompanyTaskReview {
            $review = $this->reviewRepository->create([
                'company_task_submission_id' => $submission->id,
                'company_task_assignment_id' => $submission->company_task_assignment_id,
                'company_id' => $companyId,
                'student_user_id' => $submission->student_user_id,

                'quality_score' => $data['quality_score'],
                'commitment_score' => $data['commitment_score'],
                'communication_score' => $data['communication_score'],
                'total_score' => $totalScore,

                'final_decision' => $data['final_decision'],
                'feedback' => $data['feedback'] ?? null,
                'reviewed_at' => now(),
            ]);

            $this->reviewRepository->updateSubmission(
                $submission,
                [
                    'status' => $data['final_decision'],
                ]
            );

            $this->reviewRepository->updateAssignment(
                $submission->assignment,
                $this->getAssignmentState(
                    $data['final_decision']
                )
            );

            return $review;
        });

        return $review->load([
            'submission',
            'assignment.task:id,company_id,title,deadline',
            'student:id,name,email,profile_picture_url',
            'company.users:id,name',
        ]);
    }

    public function getCompanyReview(
        int $submissionId,
        int $companyId
    ): CompanyTaskReview {
        return $this->reviewRepository
            ->findCompanyReviewOrFail(
                submissionId: $submissionId,
                companyId: $companyId
            );
    }

    private function ensureSubmissionCanBeReviewed(
        CompanyTaskSubmission $submission
    ): void {
        if (
            $submission->status === 'submitted'
            && $submission->assignment?->status === 'submitted'
        ) {
            return;
        }

        $message = match ($submission->status) {
            'approved' => 'تم اعتماد هذا التسليم مسبقًا. | This submission has already been approved.',

            'needs_changes' => 'تمت مراجعة هذا التسليم وطلب تعديلات عليه مسبقًا. | This submission has already been reviewed and changes were requested.',

            'rejected' => 'تم رفض هذا التسليم مسبقًا. | This submission has already been rejected.',

            default => 'لا يمكن مراجعة التسليم في حالته الحالية. | The submission cannot be reviewed in its current status.',
        };

        throw ValidationException::withMessages([
            'submission' => [$message],
        ]);
    }

    private function ensureReviewDoesNotExist(
        int $submissionId
    ): void {
        if ($this->reviewRepository->findBySubmission($submissionId) !== null) {
            throw ValidationException::withMessages([
                'review' => [
                    'تمت مراجعة هذا التسليم مسبقًا ولا يمكن إنشاء مراجعة أخرى له. | This submission has already been reviewed.',
                ],
            ]);
        }
    }

    private function calculateTotalScore(
        int $qualityScore,
        int $commitmentScore,
        int $communicationScore
    ): float {
        return round(
            ($qualityScore + $commitmentScore + $communicationScore) / 3,
            2
        );
    }

    private function getAssignmentState(
        string $decision
    ): array {
        return match ($decision) {
            'needs_changes' => [
                'status' => 'working',
                'submitted_at' => null,
                'completed_at' => null,
            ],

            'approved', 'rejected' => [
                'status' => 'reviewed',
                'completed_at' => now(),
            ],
        };
    }
}
