<?php

namespace App\Http\Resources\Student;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentAssessmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $totalSkills = (int) ($this->skill_sessions_count ?? 0);
        $completedSkills = (int) ($this->completed_skills_count ?? 0);
        $needsReviewSkills = (int) ($this->needs_review_skills_count ?? 0);

        return [
            'assessment_session_id' => $this->AssessmentSessionID,
            'status' => $this->Status,
            'career_path' => $this->careerPath ? [
                'career_path_id' => $this->careerPath->CareerPathID,
                'name' => $this->careerPath->Name,
            ] : null,
            'cv' => $this->cv ? [
                'cv_id' => $this->cv->CvID,
                'is_primary' => (bool) $this->cv->IsPrimary,
            ] : null,
            'progress' => [
                'total_skills' => $totalSkills,
                'completed_skills' => $completedSkills,
                'needs_review_skills' => $needsReviewSkills,
                'completion_percentage' => $totalSkills > 0
                    ? (int) round(($completedSkills / $totalSkills) * 100)
                    : 0,
            ],
            'final_results_available' => $this->Status === 'completed'
                && ! empty($this->FinalResultsJson),
            'started_at' => $this->StartedAt?->toISOString(),
            'completed_at' => $this->CompletedAt?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
