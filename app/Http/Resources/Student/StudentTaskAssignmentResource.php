<?php

namespace App\Http\Resources\Student;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentTaskAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'assignment_id' => $this->id,
            'application_id' => $this->company_task_application_id,
            'task_id' => $this->company_task_id,
            'status' => $this->status,
            'started_at' => $this->started_at,

            'application' => [
                'status' => $this->application?->status,
                'company_notes' => $this->application?->company_notes,
                'reviewed_at' => $this->application?->reviewed_at,
            ],

            'task' => [
                'id' => $this->task?->id,
                'title' => $this->task?->title,
                'description' => $this->task?->description,
                'difficulty_level' => $this->task?->difficulty_level,
                'duration_days' => $this->task?->duration_days,
                'deadline' => $this->task?->deadline,
                'status' => $this->task?->status,
                'company' => [
                    'id' => $this->task?->company?->id,
                    'name' => $this->task?->company?->users?->first()?->name,
                    'industry' => $this->task?->company?->industry,
                ],
                'skills' => $this->task?->skills?->map(fn ($skill) => [
                    'id' => $skill->id,
                    'name' => $skill->name,
                ])->values(),
            ],
        ];
    }
}
