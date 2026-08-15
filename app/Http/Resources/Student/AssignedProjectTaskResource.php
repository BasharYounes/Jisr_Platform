<?php

namespace App\Http\Resources\Student;

use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssignedProjectTaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $taskStatus = $this->status;

        if ($taskStatus instanceof BackedEnum) {
            $taskStatus = $taskStatus->value;
        }

        $assignmentStatus = $this->assignment?->status;

        if ($assignmentStatus instanceof BackedEnum) {
            $assignmentStatus = $assignmentStatus->value;
        }

        return [
            'source' => 'project_assignment',
            'id' => $this->id,
            'project_assignment_id' => $this->project_assignment_id,
            'project_task_id' => $this->project_task_id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $taskStatus,
            'estimated_hours' => $this->estimated_hours,
            'submission_url' => $this->submission_url,
            'github_branch_or_link' => $this->github_branch_or_link,
            'supervisor_feedback' => $this->supervisor_feedback,
            'started_at' => $this->started_at?->toISOString(),
            'submitted_at' => $this->submitted_at?->toISOString(),
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'order_index' => $this->order_index,

            'assignment' => $this->whenLoaded(
                'assignment',
                fn (): array => [
                    'id' => $this->assignment->id,
                    'status' => $assignmentStatus,
                    'progress_percentage' => $this->assignment->progress_percentage,
                    'assigned_at' => $this->assignment->assigned_at?->toISOString(),
                    'submitted_at' => $this->assignment->submitted_at?->toISOString(),
                    'project_template' => [
                        'id' => $this->assignment->projectTemplate?->id,
                        'title' => $this->assignment->projectTemplate?->title,
                        'level' => $this->assignment->projectTemplate?->level,
                    ],
                    'supervisor' => [
                        'id' => $this->assignment->supervisor?->id,
                        'name' => $this->assignment->supervisor?->name,
                        'email' => $this->assignment->supervisor?->email,
                    ],
                ]
            ),
        ];
    }
}
