<?php

namespace App\Http\Resources\CompanyTasks;

use App\Http\Resources\PortfolioProjectResource;
use App\Http\Resources\StudentResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyTaskApplicantDetailsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $primaryCv = $this->student?->cvs?->first();

        return [
            'application' => [
                'id' => $this->id,
                'status' => $this->status,
                'message' => $this->message,
                'github_url' => $this->github_url,
                'applied_at' => $this->applied_at?->toISOString(),
                'reviewed_at' => $this->reviewed_at?->toISOString(),
                'company_notes' => $this->company_notes,
            ],

            'task' => [
                'id' => $this->task?->id,
                'title' => $this->task?->title,
                'difficulty_level' => $this->task?->difficulty_level,

                'required_skills' => $this->whenLoaded('task', function () {
                    return $this->task?->skills?->map(function ($skill) {
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
                    });
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

            'cv' => $primaryCv ? [
                'id' => $primaryCv->CvID,

                'file_url' => $primaryCv->FileUrl
                    ? asset('storage/'.$primaryCv->FileUrl)
                    : null,

                'is_primary' => (bool) $primaryCv->IsPrimary,

                'uploaded_at' => $primaryCv->UploadedAt,
            ] : null,

            'matching' => [
                'score' => $this->match_score !== null
                    ? (float) $this->match_score
                    : null,

                'reasons' => $this->match_reasons ?? [],
            ],
        ];
    }
}
