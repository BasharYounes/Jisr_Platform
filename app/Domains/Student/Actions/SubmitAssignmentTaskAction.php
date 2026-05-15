<?php

namespace App\Domains\Student\Actions;

use App\Domains\Supervisor\Enums\ProjectAssignmentTaskStatus;
use App\Models\ProjectAssignmentTask;
use DomainException;

class SubmitAssignmentTaskAction
{
    public function execute(ProjectAssignmentTask $task, array $data): ProjectAssignmentTask
    {
        $assignment = $task->assignment;

        if (is_null($task->assigned_student_id)) {
            throw new DomainException('This task has not been assigned to any student yet.');
        }

        if ($task->assigned_student_id !== auth()->id()) {
            throw new DomainException('You can only submit tasks assigned to you.');
        }

        $StatusesAllowedForSubmission = [
            ProjectAssignmentTaskStatus::IN_PROGRESS,
            ProjectAssignmentTaskStatus::REVISION_REQUESTED,
        ];

        if (! in_array($task->status, $StatusesAllowedForSubmission)) {
            throw new DomainException('Only in-progress or revision-requested tasks can be submitted.');
        }

        $task->update([
            'status' => ProjectAssignmentTaskStatus::SUBMITTED,
            'submission_url' => $data['submission_url'] ?? $task->submission_url,
            'github_branch_or_link' => $data['github_branch_or_link'] ?? $task->github_branch_or_link,
            'submitted_at' => now(),
        ]);

        return $task->refresh()->load([
            'assignment.members.student',
            'assignment.supervisor',
            'assignment.projectTemplate',
        ]);
    }
}
