<?php

namespace App\Http\Resources\Opportunities;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentOpportunityDetailsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $match = $this->match_data ?? [];

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type,
            'company' => [
                'id' => $this->company?->id,
                'name' => $this->company?->name,
                'industry' => $this->company?->industry,
                'website' => $this->company?->website,
                'description' => $this->company?->description,
            ],
            'location' => $this->location,
            'salary_min' => $this->salary_min,
            'salary_max' => $this->salary_max,
            'status' => $this->status,
            'deadline' => $this->deadline,
            'posted_at' => $this->posted_at,

            'skills' => $this->skills->map(function ($skill): array {
                return [
                    'id' => $skill->id,
                    'name' => $skill->name,
                    'category' => $skill->category ?? null,
                    'required_level' => $skill->pivot->required_level,
                    'mandatory' => (bool) $skill->pivot->mandatory,
                    'weight' => (float) $skill->pivot->weight,
                ];
            })->values(),

            'match_score' => $match['score'] ?? 0,
            'match_label' => $this->matchLabel((float) ($match['score'] ?? 0)),
            'match_reasons' => $match['reasons'] ?? [],
            'matched_skills' => $match['matched_skills'] ?? [],
            'missing_skills' => $match['missing_skills'] ?? [],
            'missing_mandatory_skills' => $match['missing_mandatory_skills'] ?? [],
            'is_eligible_for_recommendation' => $match['is_eligible_for_recommendation'] ?? false,

            'already_applied' => (bool) ($this->already_applied ?? false),
            'application_status' => $this->application_status ?? null,
            'can_apply' => (bool) ($this->can_apply ?? false),
            'cannot_apply_reason' => $this->cannot_apply_reason ?? null,
        ];
    }

    private function matchLabel(float $score): string
    {
        return match (true) {
            $score >= 75 => 'strong_match',
            $score >= 35 => 'partial_match',
            default => 'weak_match',
        };
    }
}
