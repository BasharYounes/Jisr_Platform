<?php

namespace App\Http\Resources\Supervisor;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectTemplateApplicantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'application_id' => $this->id,
            'status' => $this->status->value,
            'message' => $this->message,
            'applied_at' => $this->applied_at,
            'reviewed_at' => $this->reviewed_at,
            'project_assignment_id' => $this->project_assignment_id,
            'student' => [
                'id' => $this->student?->id,
                'name' => $this->student?->name,
                'email' => $this->student?->email,
            ],
        ];
    }
}
