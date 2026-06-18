<?php

namespace App\Http\Resources\Opportunities;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpportunityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'company' => $this->whenLoaded('company', function (): array {
                return [
                    'id' => $this->company?->id,
                    'name' => $this->company?->name,
                ];
            }),

            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type,
            'location' => $this->location,

            'salary_min' => $this->salary_min,
            'salary_max' => $this->salary_max,

            'status' => $this->status,

            'deadline' => $this->deadline,
            'posted_at' => $this->posted_at,

            'applications_count' => $this->whenCounted('applications'),

            'skills' => $this->whenLoaded('skills', function () {
                return $this->skills->map(function ($skill): array {
                    return [
                        'id' => $skill->id,
                        'name' => $skill->name,
                        'required_level' => $skill->pivot?->required_level,
                        'mandatory' => (bool) $skill->pivot?->mandatory,
                        'weight' => $skill->pivot?->weight,
                    ];
                });
            }),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
