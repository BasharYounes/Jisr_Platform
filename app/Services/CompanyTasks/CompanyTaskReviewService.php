<?php

namespace App\Services\CompanyTasks;

use App\Interfaces\CompanyTaskReviewRepositoryInterface;
use App\Models\CompanyTaskAssignment;
use App\Models\CompanyTaskReview;
use App\Models\CompanyTaskSubmission;
use App\Events\CompanyTaskReviewCompleted;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompanyTaskReviewService
{
    public function __construct(
        private readonly CompanyTaskReviewRepositoryInterface $reviewRepository,
        private readonly TaskPortfolioProjectService $taskPortfolioProjectService,
    ) {}

    public function createReview(
        int $assignmentId,
        int $companyId,
        User $actor,
        array $data,
    ): CompanyTaskReview {
        $assignment = $this->reviewRepository
            ->findCompanyAssignmentOrFail(
                assignmentId: $assignmentId,
                companyId: $companyId
            );

        $submission = $this->getLatestSubmittedSubmissionOrFail($assignment);

        $this->ensureAssignmentCanBeReviewed($assignment, $submission);
        $this->ensureReviewDoesNotExist($submission->id);

        $totalScore = $this->calculateTotalScore(
            qualityScore: (int) $data['quality_score'],
            commitmentScore: (int) $data['commitment_score'],
            communicationScore: (int) $data['communication_score']
        );

        $review = DB::transaction(function () use (
            $assignment,
            $submission,
            $companyId,
            $data,
            $totalScore
        ): CompanyTaskReview {
            $review = $this->reviewRepository->create([
                'company_task_submission_id' => $submission->id,
                'company_task_assignment_id' => $assignment->id,
                'company_id' => $companyId,
                'student_user_id' => $assignment->student_user_id,

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
                $assignment,
                $this->getAssignmentState($data['final_decision'])
            );

            return $review;
        });

        if ($review->final_decision === 'approved') {
            $this->taskPortfolioProjectService
                ->createFromApprovedReview($review);
        }

        if (in_array(
            $review->final_decision,
            ['approved', 'rejected'],
            true
        )) {
            CompanyTaskReviewCompleted::dispatch(
                review: $review,
                actor: $actor,
            );
        }

        return $review->load([
            'submission',
            'assignment.task:id,company_id,title,deadline',
            'student:id,name,email,profile_picture_url',
            'company.users:id,name',
        ]);
    }

    public function getCompanyReview(
        int $assignmentId,
        int $companyId
    ): CompanyTaskReview {
        return $this->reviewRepository
            ->findLatestCompanyReviewByAssignmentOrFail(
                assignmentId: $assignmentId,
                companyId: $companyId
            );
    }

    private function getLatestSubmittedSubmissionOrFail(
        CompanyTaskAssignment $assignment
    ): CompanyTaskSubmission {
        $submission = $this->reviewRepository
            ->findLatestSubmittedSubmissionForAssignment($assignment->id);

        if ($submission === null) {
            throw ValidationException::withMessages([
                'submission' => [
                    'لا يوجد تسليم نهائي جاهز للمراجعة لهذا التكليف. | There is no submitted final submission ready for review for this assignment.',
                ],
            ]);
        }

        return $submission;
    }

    private function ensureAssignmentCanBeReviewed(
        CompanyTaskAssignment $assignment,
        CompanyTaskSubmission $submission
    ): void {
        if (
            $assignment->status === 'submitted'
            && $submission->status === 'submitted'
        ) {
            return;
        }

        $message = match (true) {
            $assignment->status === 'working' => 'لا يمكن مراجعة هذا التكليف قبل أن يرسل الطالب التسليم النهائي. | This assignment cannot be reviewed before the student submits the final submission.',

            $assignment->status === 'reviewed' => 'تمت مراجعة هذا التكليف مسبقًا. | This assignment has already been reviewed.',

            $submission->status === 'approved' => 'تم اعتماد هذا التسليم مسبقًا. | This submission has already been approved.',

            $submission->status === 'needs_changes' => 'تمت مراجعة هذا التسليم وطلب تعديلات عليه مسبقًا. | This submission has already been reviewed and changes were requested.',

            $submission->status === 'rejected' => 'تم رفض هذا التسليم مسبقًا. | This submission has already been rejected.',

            default => 'لا يمكن مراجعة هذا التكليف في حالته الحالية. | The assignment cannot be reviewed in its current status.',
        };

        throw ValidationException::withMessages([
            'assignment' => [$message],
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
            ],
        };
    }
}
