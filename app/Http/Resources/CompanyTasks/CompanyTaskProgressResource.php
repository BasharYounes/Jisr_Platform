<?php

namespace App\Http\Resources\CompanyTasks;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyTaskProgressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'assignment_id' => $this->company_task_assignment_id,

            'student' => [
                'id' => $this->student?->id ?? $this->student_user_id,
                'name' => $this->student?->name,
                'email' => $this->student?->email,
                'profile_picture_url' => $this->student?->profile_picture ? asset('storage/'.$this->student->profile_picture) : null,
            ],

            'progress' => [
                'title' => $this->title,
                'description' => $this->description,
                'percentage' => $this->progress_percentage,
            ],

            'links' => [
                'github_url' => $this->github_url,
                'demo_url' => $this->demo_url,
            ],

            'attachments' => collect($this->attachments ?? [])
                ->map(function (string $path): array {
                    return [
                        'url' => asset('storage/'.$path),
                    ];
                })
                ->values(),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}