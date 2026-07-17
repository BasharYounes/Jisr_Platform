<?php

namespace App\Domains\Supervisor\Actions;

use App\Domains\Supervisor\Enums\ProjectAssignmentTaskStatus;
use App\Models\ProjectTask;

class SyncProjectTaskToAssignmentsAction
{
    public function execute(ProjectTask $task): void
    {
        $task->loadMissing('template.assignments');

        $template = $task->template;

        if ($template === null) {
            return;
        }

        foreach ($template->assignments as $assignment) {
            $assignment->assignmentTasks()->firstOrCreate(
                [
                    'project_task_id' => $task->id,
                ],
                [
                    'assigned_student_id' => null,
                    'title' => $task->title,
                    'description' => $task->description,
                    'status' => ProjectAssignmentTaskStatus::TODO->value,
                    'estimated_hours' => $task->estimated_hours,
                    'github_branch_or_link' => $task->github_branch_or_link,
                    'order_index' => $task->order_index,
                ]
            );
        }
    }
}
