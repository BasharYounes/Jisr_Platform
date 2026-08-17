<?php

namespace App\Http\Resources\Opportunities;

use App\Models\OpportunityInterview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentOpportunityInterviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $hasPassed = $this->scheduled_at?->lte(now()) ?? false;

        return [
            'id' => $this->id,
            
            'application' => [
                'id' => $this->application?->id,
                'status' => $this->application?->status,
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
            
            'scheduled_at' => $this->scheduled_at,
            'meeting_type' => $this->meeting_type,
            'meeting_link' => $this->meeting_link,
            'location' => $this->location,
            'status' => $this->status,
            'has_passed' => $hasPassed,
            
            'display_status' => $this->resolveDisplayStatus(
                $hasPassed
            ),
            
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function resolveDisplayStatus(bool $hasPassed): string
    {
        if ($this->status === OpportunityInterview::STATUS_CANCELLED) {
            return 'cancelled';
        }

        if ($this->status === OpportunityInterview::STATUS_COMPLETED) {
            return 'completed';
        }

        if (
            in_array(
                $this->status,
                OpportunityInterview::SCHEDULED_STATUSES,
                true
            )
            && $hasPassed
        ) {
            return 'awaiting_company_update';
        }

        if ($this->status === OpportunityInterview::STATUS_RESCHEDULED) {
            return 'rescheduled';
        }

        return 'scheduled';
    }
}
