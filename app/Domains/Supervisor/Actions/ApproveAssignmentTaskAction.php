<?php

namespace App\Domains\Supervisor\Actions;

use App\Domains\Supervisor\Enums\ProjectAssignmentTaskStatus;
use App\Models\ProjectAssignmentTask;
use DomainException;

class ApproveAssignmentTaskAction
{
    public function execute(
        ProjectAssignmentTask $task,
        RecalculateProjectAssignmentProgressAction $recalculateProgress
    ): ProjectAssignmentTask {
        $assignment = $task->assignment;

        if ($assignment->supervisor_id !== auth()->id()) {
            throw new DomainException('You can only approve tasks assigned to you.');
        }

        if ($task->status !== ProjectAssignmentTaskStatus::UNDER_REVIEW) {
            throw new DomainException('Only under-review tasks can be approved.');
        }

        $task->update([
            'status' => ProjectAssignmentTaskStatus::DONE,
            'completed_at' => now(),
        ]);

        $recalculateProgress->execute($assignment);

        return $task->refresh()->load([
            'assignment.members.student',
            'assignment.supervisor',
            'assignment.projectTemplate',
        ]);
    }
}
