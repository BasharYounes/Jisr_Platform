<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MentorApplicationResource extends JsonResource
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
            'rejection_reason' => $this->rejection_reason,
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
