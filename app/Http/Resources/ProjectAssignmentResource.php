<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'status' => $this->status,

            'progress_percentage' => $this->progress_percentage,

            'submission_url' => $this->submission_url,

            'github_link' => $this->github_link,

            'assigned_at' => $this->assigned_at,

            'submitted_at' => $this->submitted_at,
            'assignment_tasks' => [
                'assigned_student' => [
                    'id' => $this->student?->id,
                    'name' => $this->student?->name,
                    'email' => $this->student?->email,
                ],
                'tasks' => ProjectAssignmentTaskResource::collection(
                    $this->whenLoaded('assignmentTasks')
                ),
            ],

            'supervisor' => [
                'id' => $this->supervisor?->id,
                'name' => $this->supervisor?->name,
            ],

            'project_template' => [
                'id' => $this->projectTemplate?->id,
                'title' => $this->projectTemplate?->title,
                'level' => $this->projectTemplate?->level,
            ],

            // 'assignment_tasks' => ProjectAssignmentTaskResource::collection(
            //     $this->whenLoaded('assignmentTasks')
            // ),
        ];
    }
}
