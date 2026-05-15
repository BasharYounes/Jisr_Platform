<?php

namespace App\Domains\Student\Actions;

use App\Domains\Supervisor\Enums\ProjectAssignmentStatus;
use App\Domains\Supervisor\Enums\ProjectAssignmentTaskStatus;
use App\Events\ProjectAssignmentStatusChanged;
use App\Models\ProjectAssignmentTask;
use DomainException;

class StartAssignmentTaskAction
{
    public function execute(ProjectAssignmentTask $task): ProjectAssignmentTask
    {
        $assignment = $task->assignment;

        if ($task->assigned_student_id !== auth()->id()) {
            throw new DomainException('You can only start tasks assigned to you.');
        }

        if (! in_array($task->status, [
            ProjectAssignmentTaskStatus::TODO,
            ProjectAssignmentTaskStatus::REVISION_REQUESTED,
        ], true)) {
            throw new DomainException('Only todo or revision-requested tasks can be started.');
        }

        if (is_null($task->assigned_student_id)) {
            throw new DomainException('This task has not been assigned to any student yet.');
        }

        $task->update([
            'status' => ProjectAssignmentTaskStatus::IN_PROGRESS,
            'started_at' => now(),
        ]);

        if ($assignment->status === ProjectAssignmentStatus::ASSIGNED) {
            $oldStatus = $assignment->status->value;

            $assignment->update([
                'status' => ProjectAssignmentStatus::IN_PROGRESS,
                'progress_percentage' => 10,
            ]);

            event(new ProjectAssignmentStatusChanged(
                tasks: $task->refresh(),
                oldStatus: $oldStatus,
                newStatus: ProjectAssignmentStatus::IN_PROGRESS->value,
                changedBy: auth()->id()
            ));
        }

        return $task->refresh()->load([
            'assignment.members.student',
            'assignment.supervisor',
            'assignment.projectTemplate',
        ]);
    }
}
