<?php

namespace App\Domains\Supervisor\Actions;

use App\Domains\Supervisor\Enums\ProjectAssignmentTaskStatus;
use App\Models\ProjectAssignmentTask;
use DomainException;

class RequestAssignmentTaskRevisionAction
{
    public function execute(ProjectAssignmentTask $task, string $feedback): ProjectAssignmentTask
    {
        $assignment = $task->assignment;

        if ($assignment->supervisor_id !== auth()->id()) {
            throw new DomainException('You can only request revision for tasks assigned to you.');
        }

        if ($task->status !== ProjectAssignmentTaskStatus::UNDER_REVIEW) {
            throw new DomainException('Only under-review tasks can be returned for revision.');
        }

        $task->update([
            'status' => ProjectAssignmentTaskStatus::REVISION_REQUESTED,
            'supervisor_feedback' => $feedback,
            'reviewed_at' => now(),
        ]);

        return $task->refresh()->load([
            'assignment.members.student',
            'assignment.supervisor',
            'assignment.projectTemplate',
        ]);
    }
}
