<?php

namespace App\Http\Resources\CompanyTasks;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyTaskAssignmentCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'assignment_id' => $this->id,
            'status' => $this->status,

            'task' => [
                'id' => $this->task?->id,
                'title' => $this->task?->title,
                'difficulty_level' => $this->task?->difficulty_level,
                'deadline' => $this->task?->deadline?->toISOString(),
            ],

            'student' => [
                'id' => $this->student?->id,
                'name' => $this->student?->name,
                'email' => $this->student?->email,
                'profile_picture_url' => $this->student?->profile_picture_url
                    ? asset('storage/'.$this->student->profile_picture_url)
                    : null,
            ],

            'matching' => [
                'score' => $this->application?->match_score !== null
                    ? (float) $this->application->match_score
                    : null,
                // 'reasons' => $this->application?->match_reasons ?? [],
            ],

            'started_at' => $this->started_at?->toISOString(),
            'submitted_at' => $this->submitted_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
        ];
    }
}