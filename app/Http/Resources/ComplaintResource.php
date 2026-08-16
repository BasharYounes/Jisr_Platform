<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComplaintResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'target_type' => $this->targetType(),
            'reported_user' => $this->reported_user_id !== null
                ? $this->whenLoaded(
                    'reportedUser',
                    fn (): array => [
                        'id' => $this->reportedUser?->id,
                        'name' => $this->reportedUser?->name,
                        'email' => $this->reportedUser?->email,
                    ]
                )
                : null,
            'reported_mentor' => $this->reported_mentor_profile_id !== null
                ? $this->whenLoaded(
                    'reportedMentorProfile',
                    fn (): array => [
                        'id' => $this->reportedMentorProfile?->id,
                        'full_name' => $this->reportedMentorProfile?->full_name,
                        'email' => $this->reportedMentorProfile?->email,
                        'specialization' => $this->reportedMentorProfile?->specialization,
                        'professional_title' => $this->reportedMentorProfile?->professional_title,
                        'internal_user_id' => $this->reportedMentorProfile?->user_id,
                    ]
                )
                : null,
            'context' => [
                'type' => $this->context_type?->value,
                'id' => $this->context_id,
            ],
            'reason' => $this->reason,
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
    }
}
