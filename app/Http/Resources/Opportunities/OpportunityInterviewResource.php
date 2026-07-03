<?php

namespace App\Http\Resources\Opportunities;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpportunityInterviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'application' => [
                'id' => $this->application?->id,
                'status' => $this->application?->status,
                'match_score' => $this->application?->match_score,
                'cover_letter' => $this->application?->cover_letter,
            ],

            'opportunity' => [
                'id' => $this->opportunity?->id,
                'title' => $this->opportunity?->title,
                'type' => $this->opportunity?->type,
                'status' => $this->opportunity?->status,
            ],

            'company' => [
                'id' => $this->company?->id,
                'name' => $this->company?->name,
                'industry' => $this->company?->industry,
            ],

            'student' => [
                'id' => $this->student?->id,
                'name' => $this->student?->name,
                'email' => $this->student?->email,
                'profile_picture_url' => $this->student?->ProfilePictureUrl ?? null,
                'university' => $this->application?->user?->studentProfile?->University ?? null,
                'major' => $this->application?->user?->studentProfile?->Major ?? null,
            ],

            'scheduled_at' => $this->scheduled_at,
            'meeting_type' => $this->meeting_type,
            'meeting_link' => $this->meeting_link,
            'location' => $this->location,
            'status' => $this->status,
            'notes' => $this->notes,

            'conversation' => $this->when(
                isset($this->conversation_data),
                $this->conversation_data
            ),

            'actions' => [
                'can_reschedule' => in_array($this->status, ['scheduled', 'rescheduled'], true),
                'can_cancel' => in_array($this->status, ['scheduled', 'rescheduled'], true),
                'can_complete' => in_array($this->status, ['scheduled', 'rescheduled'], true),
            ],

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}