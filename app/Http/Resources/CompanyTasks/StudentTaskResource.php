<?php

namespace App\Http\Resources\CompanyTasks;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentTaskResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'company' => [
                'id' => $this->company?->id,
                'name' => $this->company?->users?->first()?->name,
                'industry' => $this->company?->industry,
            ],

            'title' => $this->title,
            'description' => $this->description,

            'difficulty_level' => $this->difficulty_level,
            'duration_days' => $this->duration_days,
            'deadline' => $this->deadline?->toISOString(),

            'deliverables' => $this->deliverables,
            'acceptance_criteria' => $this->acceptance_criteria,

            'submission_type' => $this->submission_type,
            'status' => $this->status,

            'match_score' => $this->when(
                isset($this->match_score),
                fn () => (float) $this->match_score
            ),

            'match_reasons' => $this->when(
                isset($this->match_reasons),
                fn () => $this->match_reasons
            ),

            'skills' => $this->whenLoaded('skills', function () {
                return $this->skills->map(function ($skill) {
                    return [
                        'id' => $skill->id,
                        'name' => $skill->name,
                        'category' => $skill->category ?? null,
                        'required_level' => $skill->pivot->required_level,
                        'weight' => (float) $skill->pivot->weight,
                        'mandatory' => (bool) $skill->pivot->mandatory,
                    ];
                });
            }),

            'published_at' => $this->published_at?->toISOString(),
        ];
    }
}
