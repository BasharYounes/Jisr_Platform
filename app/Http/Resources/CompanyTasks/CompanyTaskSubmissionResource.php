<?php

namespace App\Http\Resources\CompanyTasks;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyTaskSubmissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'assignment_id' => $this->company_task_assignment_id,
            'status' => $this->status,

            'student' => [
                'id' => $this->student?->id ?? $this->student_user_id,
                'name' => $this->student?->name,
                'email' => $this->student?->email,

                'profile_picture_url' => $this->student?->profile_picture_url
                    ? asset('storage/'.$this->student->profile_picture_url)
                    : null,
            ],

            'task' => [
                'id' => $this->assignment?->task?->id,
                'title' => $this->assignment?->task?->title,
                'deadline' => $this->assignment?->task?->deadline?->toISOString(),
                'submission_type' => $this->assignment?->task?->submission_type,
            ],

            'submission' => [
                'github_url' => $this->github_url,
                'demo_url' => $this->demo_url,

                'zip_file' => $this->zip_file_path
                    ? [
                        'name' => basename($this->zip_file_path),
                        'url' => asset('storage/'.$this->zip_file_path),
                    ]
                    : null,

                'notes' => $this->notes,
            ],

            'submitted_at' => $this->submitted_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
