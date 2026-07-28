<?php

namespace App\Listeners;

use App\Domains\Supervisor\Enums\ProjectAssignmentStatus;
use App\Events\ProjectAssignmentStatusChanged;
use App\Models\PortfolioProject;

class CreatePortfolioProjectWhenAssignmentCompleted
{
    public function handle(
        ProjectAssignmentStatusChanged $event
    ): void {
        if (
            $event->newStatus
            !== ProjectAssignmentStatus::COMPLETED->value
        ) {
            return;
        }

        $assignment = $event->assignment->loadMissing([
            'projectTemplate',
            'evaluations',
            'members',
        ]);

        $evaluationsByStudent = $assignment
            ->evaluations
            ->keyBy('student_id');

        $activeMembers = $assignment
            ->members
            ->where('status', 'active');

        foreach ($activeMembers as $member) {
            $studentEvaluation = $evaluationsByStudent->get(
                $member->student_id
            );

            PortfolioProject::updateOrCreate(
                [
                    'user_id' => $member->student_id,
                    'portfolioable_type' => $assignment->getMorphClass(),
                    'portfolioable_id' => $assignment->id,
                ],
                [
                    'source' => 'project_assignment',
                    'title' => $assignment->projectTemplate->title,
                    'description' => $assignment->projectTemplate->description,
                    'project_url' => $assignment->github_link
                        ?: $assignment->submission_url,
                    'completion_date' => now(),
                    'grade' => $studentEvaluation?->final_grade,
                ]
            );
        }
    }
}
