<?php

namespace App\Http\Resources\CompanyTasks;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentTaskCardResource extends JsonResource
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
            'difficulty_level' => $this->difficulty_level,
            'duration_days' => $this->duration_days,
            'deadline' => $this->deadline?->toISOString(),

            // 'skills' => $this->whenLoaded('skills', function () {
            //     return $this->skills->map(function ($skill) {
            //         return [
            //             'id' => $skill->id,
            //             'name' => $skill->name,
            //             'category' => $skill->category ?? null,
            //             'mandatory' => (bool) $skill->pivot->mandatory,
            //         ];
            //     });
            // }),

            'match_score' => $this->when(
                isset($this->match_score),
                fn () => (float) $this->match_score
            ),

            // 'published_at' => $this->published_at?->toISOString(),
        ];
    }
}