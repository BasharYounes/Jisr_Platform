<?php

namespace App\Domains\Supervisor\Actions;

use App\Domains\Supervisor\Enums\ProjectAssignmentStatus;
use App\Domains\Supervisor\Enums\ProjectAssignmentTaskStatus;
use App\Models\ProjectAssignment;

class RecalculateProjectAssignmentProgressAction
{
    public function execute(ProjectAssignment $assignment): ProjectAssignment
    {
        $totalTasks = $assignment->assignmentTasks()->count();

        if ($totalTasks === 0) {
            $assignment->update([
                'progress_percentage' => 0,
            ]);

            return $assignment->refresh();
        }

        $doneTasks = $assignment->assignmentTasks()
            ->where('status', ProjectAssignmentTaskStatus::DONE->value)
            ->count();

        $progress = (int) round(($doneTasks / $totalTasks) * 100);

        $updates = [
            'progress_percentage' => $progress,
        ];

        $wasNotReady = $assignment->status !== ProjectAssignmentStatus::UNDER_REVIEW;

        if ($progress === 100) {
            $updates['status'] = ProjectAssignmentStatus::UNDER_REVIEW;
        }

        $assignment->update($updates);

        if ($progress === 100 && $wasNotReady) {
            event(new \App\Events\ProjectAssignmentReadyForEvaluation(
                $assignment->refresh()
            ));
        }

        return $assignment->refresh();
    }
}
