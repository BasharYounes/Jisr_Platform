<?php

namespace App\Domains\Supervisor\Actions;

use App\Domains\Supervisor\Enums\ProjectAssignmentStatus;
// use App\Domains\Supervisor\Enums\ProjectAssignmentTaskStatus;
use App\Models\ProjectAssignment;
use App\Models\ProjectTemplate;
// use DomainException;
use Illuminate\Support\Facades\DB;

class AssignProjectAction
{
    public function execute(array $data): ProjectAssignment
    {
        return DB::transaction(function () use ($data) {
            $projectTemplate = ProjectTemplate::query()
                ->with('tasks')
                ->findOrFail($data['project_template_id']);

            // if ($projectTemplate->tasks->isEmpty()) {
            //     throw new DomainException('This project template has no tasks.');
            // }

            $assignment = ProjectAssignment::create([
                'project_template_id' => $projectTemplate->id,
                'supervisor_id' => auth()->id(),
                'status' => ProjectAssignmentStatus::ASSIGNED->value,
                'progress_percentage' => 0,
                'assigned_at' => now(),
            ]);

            // foreach ($data['students'] as $student) {
            //     $assignment->members()->create([
            //         'student_id' => $student['student_id'],
            //         'role' => $student['role'] ?? null,
            //         'status' => 'active',
            //     ]);
            // }

            // foreach ($projectTemplate->tasks as $task) {
            //     $assignment->assignmentTasks()->create([
            //         'project_task_id' => $task->id,
            //         'assigned_student_id' => null,
            //         'title' => $task->title,
            //         'description' => $task->description,
            //         'status' => ProjectAssignmentTaskStatus::TODO->value,
            //         'estimated_hours' => $task->estimated_hours,
            //         'github_branch_or_link' => $task->github_branch_or_link,
            //         'order_index' => $task->order_index,
            //     ]);
            // }

            return $assignment->load([
                'supervisor',
                'projectTemplate',
                'members.student',
                'assignmentTasks.assignedStudent',
            ]);
        });
    }
}
