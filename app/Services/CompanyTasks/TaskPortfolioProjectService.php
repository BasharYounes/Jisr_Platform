<?php

namespace App\Services\CompanyTasks;

use App\Interfaces\PortfolioProjectRepositoryInterface;
use App\Models\CompanyTaskAssignment;
use App\Models\CompanyTaskReview;
use App\Models\PortfolioProject;

class TaskPortfolioProjectService
{
    public function __construct(
        private readonly PortfolioProjectRepositoryInterface $portfolioProjectRepository
    ) {}

    public function createFromApprovedReview(
        CompanyTaskReview $review
    ): ?PortfolioProject {
        if ($review->final_decision !== 'approved') {
            return null;
        }

        $review->loadMissing([
            'assignment.task',
            'submission',
        ]);

        $assignment = $review->assignment;
        $task = $assignment?->task;
        $submission = $review->submission;

        if ($assignment === null || $task === null || $submission === null) {
            return null;
        }

        $existingProject = $this->portfolioProjectRepository
            ->findByPortfolioable(
                userId: $review->student_user_id,
                portfolioableType: CompanyTaskAssignment::class,
                portfolioableId: $assignment->id
            );

        if ($existingProject !== null) {
            return $existingProject;
        }

        return $this->portfolioProjectRepository->create([
            'user_id' => $review->student_user_id,

            'portfolioable_type' => CompanyTaskAssignment::class,
            'portfolioable_id' => $assignment->id,

            'source' => 'company_task_assignment',

            'title' => $task->title,

            'description' => $this->buildDescription(
                review: $review,
                submissionNotes: $submission->notes
            ),

            'project_url' => $submission->demo_url
                ?: $submission->github_url,

            'completion_date' => $review->reviewed_at ?? now(),

            'grade' => $review->total_score,
        ]);
    }

    private function buildDescription(
        CompanyTaskReview $review,
        ?string $submissionNotes
    ): string {
        $parts = [];

        if (! empty($review->feedback)) {
            $parts[] = 'Company feedback: '.$review->feedback;
        }

        if (! empty($submissionNotes)) {
            $parts[] = 'Student notes: '.$submissionNotes;
        }

        return implode("\n\n", $parts);
    }
}
