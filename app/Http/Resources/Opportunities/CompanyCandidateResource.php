<?php

namespace App\Http\Resources\Opportunities;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyCandidateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'application_id' => $this->id,

            'student' => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
                'profile_picture_url' => $this->user?->ProfilePictureUrl ?? null,
                'university' => $this->user?->studentProfile?->University ?? null,
                'major' => $this->user?->studentProfile?->Major ?? null,
                'graduation_year' => $this->user?->studentProfile?->GraduationYear ?? null,
            ],

            'opportunity' => [
                'id' => $this->opportunity?->id,
                'title' => $this->opportunity?->title,
                'type' => $this->opportunity?->type,
                'status' => $this->opportunity?->status,
            ],

            'cv' => $this->cv ? [
                'id' => $this->cv->CvID,
                'file_url' => $this->cv->FileUrl
                    ? asset('storage/'.$this->cv->FileUrl)
                    : null,
                'is_primary' => (bool) $this->cv->IsPrimary,
                'uploaded_at' => $this->cv->UploadedAt,
            ] : null,

            'cover_letter' => $this->cover_letter,

            'application_status' => $this->status,
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

            'actions' => [
                'can_schedule_interview' => $this->canScheduleInterview(),
                'can_view_interview' => $this->interview !== null,
                'can_accept' => $this->canAccept(),
                'can_reject' => $this->canReject(),
            ],

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

    private function canScheduleInterview(): bool
    {
        return $this->status === 'pending'
            && $this->interview === null;
    }

    private function canAccept(): bool
    {
        return $this->status === 'pending'
            && $this->interview?->status === 'completed';
    }

    private function canReject(): bool
    {
        return $this->status === 'pending';
    }
}