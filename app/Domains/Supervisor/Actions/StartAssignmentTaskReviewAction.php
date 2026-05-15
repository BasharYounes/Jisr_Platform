<?php

namespace App\Domains\Supervisor\Actions;

use App\Domains\Supervisor\Enums\ProjectAssignmentTaskStatus;
use App\Models\ProjectAssignmentTask;
use DomainException;

class StartAssignmentTaskReviewAction
{
    public function execute(ProjectAssignmentTask $task): ProjectAssignmentTask
    {
        $assignment = $task->assignment;

        if ($assignment->supervisor_id !== auth()->id()) {
            throw new DomainException('You can only review tasks assigned to you.');
        }

        if ($task->status !== ProjectAssignmentTaskStatus::SUBMITTED) {
            throw new DomainException('Only submitted tasks can be reviewed.');
        }

        $task->update([
            'status' => ProjectAssignmentTaskStatus::UNDER_REVIEW,
            'reviewed_at' => now(),
        ]);

        return $task->refresh()->load([
            'assignment.members.student',
            'assignment.supervisor',
            'assignment.projectTemplate',
        ]);
    }
}
