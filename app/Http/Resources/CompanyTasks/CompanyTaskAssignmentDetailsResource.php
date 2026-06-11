<?php

namespace App\Http\Resources\CompanyTasks;

use App\Http\Resources\PortfolioProjectResource;
use App\Http\Resources\StudentResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyTaskAssignmentDetailsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'assignment' => [
                'id' => $this->id,
                'status' => $this->status,
                'started_at' => $this->started_at?->toISOString(),
                'submitted_at' => $this->submitted_at?->toISOString(),
                'completed_at' => $this->completed_at?->toISOString(),
            ],

            'application' => [
                'id' => $this->application?->id,
                'status' => $this->application?->status,
                'message' => $this->application?->message,
                'github_url' => $this->application?->github_url,
                'applied_at' => $this->application?->applied_at?->toISOString(),
                'reviewed_at' => $this->application?->reviewed_at?->toISOString(),
                'company_notes' => $this->application?->company_notes,
            ],

            'task' => [
                'id' => $this->task?->id,
                'title' => $this->task?->title,
                'description' => $this->task?->description,
                'difficulty_level' => $this->task?->difficulty_level,
                'deadline' => $this->task?->deadline?->toISOString(),
                'required_skills' => $this->task?->skills?->map(function ($skill) {
                    return [
                        'id' => $skill->id,
                        'name' => $skill->name,
                        'category' => $skill->category,
                        'required_level' => $skill->pivot?->required_level,
                        'weight' => $skill->pivot?->weight !== null
                            ? (float) $skill->pivot->weight
                            : null,
                        'mandatory' => (bool) $skill->pivot?->mandatory,
                    ];
                }),
            ],

            'student' => [
                'id' => $this->student?->id,
                'profile' => $this->student?->studentProfile
                    ? new StudentResource($this->student->studentProfile)
                    : null,
                'skills' => $this->student?->skills?->map(function ($skill) {
                    return [
                        'id' => $skill->id,
                        'name' => $skill->name,
                        'category' => $skill->category,
                        'proficiency_level' => $skill->pivot?->ProficiencyLevel,
                        'confidence_score' => $skill->pivot?->ConfidenceScore !== null
                            ? (float) $skill->pivot->ConfidenceScore
                            : null,
                        'verified' => (bool) $skill->pivot?->Verified,
                    ];
                }),
                'portfolio_projects' => PortfolioProjectResource::collection(
                    $this->student?->portfolioProjects ?? collect()
                ),
            ],

            'matching' => [
                'score' => $this->application?->match_score !== null
                    ? (float) $this->application->match_score
                    : null,
                'reasons' => $this->application?->match_reasons ?? [],
            ],

            'progress_updates' => $this->progressUpdates,
            'submissions' => $this->submissions,
            'reviews' => $this->reviews,
        ];
    }
}
