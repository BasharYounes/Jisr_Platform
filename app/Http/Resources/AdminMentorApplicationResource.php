<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminMentorApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source' => $this->source?->value,
            'status' => $this->status?->value,

            'full_name' => $this->full_name,
            'email' => $this->email,
            'whatsapp_number' => $this->whatsapp_number,

            'specialization' => $this->specialization,
            'professional_title' => $this->professional_title,
            'expertise' => $this->expertise,
            'bio' => $this->bio,

            'linkedin_url' => $this->linkedin_url,
            'github_or_portfolio_url' => $this->github_or_portfolio_url,
            'mentoring_topics' => $this->mentoring_topics ?? [],

            'cv_available' => filled($this->cv_path),
            'cv_download_endpoint' => filled($this->cv_path)
                ? "/api/admin/mentor-applications/{$this->id}/cv"
                : null,

            'submitted_by' => $this->whenLoaded(
                'submittedBy',
                fn () => $this->submittedBy
                    ? [
                        'id' => $this->submittedBy->id,
                        'name' => $this->submittedBy->name,
                        'email' => $this->submittedBy->email,
                    ]
                    : null
            ),

            'company' => $this->whenLoaded(
                'company',
                fn () => $this->company
                    ? [
                        'id' => $this->company->id,
                        'industry' => $this->company->industry,
                        'website' => $this->company->website,
                    ]
                    : null
            ),

            'skills' => $this->whenLoaded(
                'skills',
                fn () => $this->skills
                    ->map(fn ($skill) => [
                        'id' => $skill->id,
                        'name' => $skill->name,
                        'category' => $skill->category,
                    ])
                    ->values()
                    ->all()
            ),

            'reviewed_by' => $this->whenLoaded(
                'reviewedBy',
                fn () => $this->reviewedBy
                    ? [
                        'id' => $this->reviewedBy->id,
                        'name' => $this->reviewedBy->name,
                        'email' => $this->reviewedBy->email,
                    ]
                    : null
            ),

            'rejection_reason' => $this->rejection_reason,
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
