<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentMentorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $matchingSkillIds = collect(
            $this->getAttribute('matching_skill_ids') ?? []
        )->map(fn ($id) => (int) $id);

        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'whatsapp_number' => $this->whatsapp_number,

            'specialization' => $this->specialization,
            'professional_title' => $this->professional_title,
            'expertise' => $this->expertise,
            'bio' => $this->bio,

            'linkedin_url' => $this->linkedin_url,
            'github_or_portfolio_url' => $this->github_or_portfolio_url,
            'mentoring_topics' => $this->mentoring_topics ?? [],

            'skills' => $this->whenLoaded(
                'skills',
                fn () => $this->skills
                    ->map(fn ($skill) => [
                        'id' => $skill->id,
                        'name' => $skill->name,
                        'category' => $skill->category,
                    ])
                    ->values()
                    ->all()
            ),

            'company' => $this->whenLoaded(
                'company',
                fn () => $this->company
                    ? [
                        'id' => $this->company->id,
                        'industry' => $this->company->industry,
                        'website' => $this->company->website,
                    ]
                    : null
            ),

            'recommendation' => [
                'is_recommended' => (bool) (
                    $this->getAttribute('is_recommended') ?? false
                ),
                'specialization_match' => (bool) (
                    $this->getAttribute('specialization_match') ?? false
                ),
                'matching_skill_count' => (int) (
                    $this->getAttribute('matching_skill_count') ?? 0
                ),
                'matching_skills' => $this->relationLoaded('skills')
                    ? $this->skills
                        ->filter(
                            fn ($skill) => $matchingSkillIds
                                ->contains((int) $skill->id)
                        )
                        ->map(fn ($skill) => [
                            'id' => $skill->id,
                            'name' => $skill->name,
                        ])
                        ->values()
                        ->all()
                    : [],
            ],
        ];
    }
}
