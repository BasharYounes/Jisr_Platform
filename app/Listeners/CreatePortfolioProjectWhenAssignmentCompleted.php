<?php

namespace App\Listeners;

use App\Domains\Supervisor\Enums\ProjectAssignmentStatus;
use App\Events\ProjectAssignmentStatusChanged;
use App\Models\PortfolioProject;

class CreatePortfolioProjectWhenAssignmentCompleted
{
    public function handle(ProjectAssignmentStatusChanged $event): void
    {
        if ($event->newStatus !== ProjectAssignmentStatus::COMPLETED->value) {
            return;
        }

        $assignment = $event->assignment->loadMissing([
            'projectTemplate',
            'evaluation',
            'members.student',
        ]);

        foreach ($assignment->members as $member) {
            PortfolioProject::updateOrCreate(
                [
                    'user_id' => $member->student_id,
                    'project_assignment_id' => $assignment->id,
                ],
                [
                    'title' => $assignment->projectTemplate?->title,
                    'description' => $assignment->projectTemplate?->description,
                    'completion_date' => now(),
                    'grade' => $assignment->evaluation?->final_grade,
                ]
            );
        }
    }
}
