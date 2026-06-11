<?php

namespace App\Http\Resources\CompanyTasks;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyTaskApplicantCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'application_id' => $this->id,

            'student' => [
                'id' => $this->student?->id,
                'name' => $this->student?->name,
                'profile_picture_url' => $this->student?->profile_picture_url
                    ? asset('storage/'.$this->student->profile_picture_url)
                    : null,
            ],

            'portfolio_projects_count' => (int) ($this->student?->portfolio_projects_count ?? 0),

            'status' => $this->status,

            'match_score' => $this->when(
                $this->match_score !== null,
                fn () => (float) $this->match_score
            ),

            'applied_at' => $this->applied_at?->toISOString(),
        ];
    }
}
