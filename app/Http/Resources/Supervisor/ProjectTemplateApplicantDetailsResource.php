<?php

namespace App\Http\Resources\Supervisor;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectTemplateApplicantDetailsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'application_id' => $this->id,
            'status' => $this->status->value,
            'message' => $this->message,
            'supervisor_notes' => $this->supervisor_notes,
            'applied_at' => $this->applied_at,
            'reviewed_at' => $this->reviewed_at,
            'project_assignment_id' => $this->project_assignment_id,
            'project_template' => [
                'id' => $this->projectTemplate?->id,
                'title' => $this->projectTemplate?->title,
                'description' => $this->projectTemplate?->description,
                'level' => $this->projectTemplate?->level,
                'max_students' => $this->projectTemplate?->max_students,
            ],
            'student' => [
                'id' => $this->student?->id,
                'name' => $this->student?->name,
                'email' => $this->student?->email,
            ],
            'project_assignment' => $this->when(
                $this->relationLoaded('projectAssignment') && $this->projectAssignment,
                fn () => [
                    'id' => $this->projectAssignment->id,
                    'status' => $this->projectAssignment->status->value,
                    'progress_percentage' => $this->projectAssignment->progress_percentage,
                    'assigned_at' => $this->projectAssignment->assigned_at,
                ]
            ),
        ];
    }
}
