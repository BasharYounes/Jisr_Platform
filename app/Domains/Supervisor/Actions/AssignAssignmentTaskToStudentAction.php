<?php

namespace App\Domains\Supervisor\Actions;

use App\Models\ProjectAssignmentTask;
use DomainException;

class AssignAssignmentTaskToStudentAction
{
    public function execute(
        ProjectAssignmentTask $task,
        int $studentId
    ): ProjectAssignmentTask {
        $assignment = $task->assignment;

        if ($assignment->supervisor_id !== auth()->id()) {
            throw new DomainException('You can only assign tasks in your own project.');
        }

        $isMember = $assignment->members()
            ->where('student_id', $studentId)
            ->where('status', 'active')
            ->exists();

        if (! $isMember) {
            throw new DomainException('This student is not an active member of this project assignment.');
        }

        $task->update([
            'assigned_student_id' => $studentId,
        ]);

        return $task->refresh()->load([
            'assignment.projectTemplate',
            'assignment.members.student',
            'assignedStudent',
        ]);
    }
}
