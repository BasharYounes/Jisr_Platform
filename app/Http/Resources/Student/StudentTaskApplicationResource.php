<?php

namespace App\Http\Resources\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentTaskApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'application_id' => $this->id,
            'task_id' => $this->company_task_id,
            'status' => $this->status,
            'cover_letter' => $this->cover_letter,
            'company_notes' => $this->company_notes,
            'match_score' => $this->match_score,
            'applied_at' => $this->applied_at,
            'reviewed_at' => $this->reviewed_at,

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