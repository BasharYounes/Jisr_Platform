<?php

namespace App\Http\Resources\CompanyTasks;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyTaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'company' => [
                'id' => $this->company?->id,
                'name' => $this->company?->name,
                'industry' => $this->company?->industry,
            ],

            'title' => $this->title,
            'description' => $this->description,

            'difficulty_level' => $this->difficulty_level,
            'duration_days' => $this->duration_days,
            'deadline' => $this->deadline?->toISOString(),

            'max_applicants' => $this->max_applicants,
            'max_accepted_students' => $this->max_accepted_students,

            'deliverables' => $this->deliverables,
            'acceptance_criteria' => $this->acceptance_criteria,

            'submission_type' => $this->submission_type,
            'status' => $this->status,

            'published_at' => $this->published_at?->toISOString(),

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

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}