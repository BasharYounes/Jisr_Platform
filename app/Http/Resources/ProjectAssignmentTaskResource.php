<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectAssignmentTaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'project_task_id' => $this->project_task_id,

            'title' => $this->title,
            'description' => $this->description,

            'status' => $this->status?->value ?? $this->status,

            'estimated_hours' => $this->estimated_hours,

            'submission_url' => $this->submission_url,
            'github_branch_or_link' => $this->github_branch_or_link,

            'supervisor_feedback' => $this->supervisor_feedback,

            'assigned_student' => [
                'id' => $this->assignedStudent?->id,
                'name' => $this->assignedStudent?->name,
                'email' => $this->assignedStudent?->email,
            ],

            'started_at' => $this->started_at,
            'submitted_at' => $this->submitted_at,
            'reviewed_at' => $this->reviewed_at,
            'completed_at' => $this->completed_at,

            'order_index' => $this->order_index,
        ];
    }
}
