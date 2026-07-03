<?php

namespace App\Http\Resources\Opportunities;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpportunityApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'opportunity' => [
                'id' => $this->opportunity?->id,
                'title' => $this->opportunity?->title,
                'type' => $this->opportunity?->type,
                'status' => $this->opportunity?->status,
                'deadline' => $this->opportunity?->deadline,
                'company' => [
                    'id' => $this->opportunity?->company?->id,
                    'name' => $this->opportunity?->company?->name,
                    'industry' => $this->opportunity?->company?->industry,
                ],
            ],

            'cv' => $this->cv ? [
                'id' => $this->cv->CvID,
                'file_url' => $this->cv->FileUrl ?? null,
                'is_primary' => $this->cv->IsPrimary ?? null,
                'uploaded_at' => $this->cv->UploadedAt ?? null,
            ] : null,

            'cover_letter' => $this->cover_letter,
            'status' => $this->status,
            'display_status' => $this->displayStatus(),

            'match_score' => $this->match_score,
            'match_reasons' => $this->normalizeMatchReasons(),

            'interview' => $this->interview ? [
                'id' => $this->interview->id,
                'scheduled_at' => $this->interview->scheduled_at,
                'meeting_type' => $this->interview->meeting_type,
                'meeting_link' => $this->interview->meeting_link,
                'location' => $this->interview->location,
                'status' => $this->interview->status,
                'notes' => $this->interview->notes,
            ] : null,

            'reviewed_at' => $this->reviewed_at,
            'reviewer_notes' => $this->reviewer_notes,
            'applied_at' => $this->applied_at,
            'created_at' => $this->created_at,
        ];
    }

    private function normalizeMatchReasons(): array
    {
        if (is_array($this->match_reasons)) {
            return $this->match_reasons;
        }

        if (is_string($this->match_reasons)) {
            return json_decode($this->match_reasons, true) ?: [];
        }

        return [];
    }

    private function displayStatus(): string
    {
        if ($this->status === 'pending' && $this->interview === null) {
            return 'pending_review';
        }

        if ($this->status === 'pending' && $this->interview?->status === 'scheduled') {
            return 'interview_scheduled';
        }

        if ($this->status === 'pending' && $this->interview?->status === 'rescheduled') {
            return 'interview_rescheduled';
        }

        if ($this->status === 'pending' && $this->interview?->status === 'completed') {
            return 'waiting_final_decision';
        }

        return $this->status;
    }
}
