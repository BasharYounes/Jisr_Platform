<?php

namespace App\Http\Resources\Student;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentCvResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $analysis = $this->relationLoaded('latestAnalysis')
            ? $this->latestAnalysis
            : null;

        $skillsCount = null;

        if ($analysis) {
            $skillsCount = $analysis->relationLoaded('extractedSkills')
                ? $analysis->extractedSkills->count()
                : (int) ($analysis->extracted_skills_count ?? 0);
        }

        return [
            'cv_id' => $this->CvID,
            'file_url' => $this->FileUrl
                ? asset('storage/'.$this->FileUrl)
                : null,
            'is_primary' => (bool) $this->IsPrimary,
            'uploaded_at' => $this->UploadedAt?->toISOString(),
            'has_analysis' => $analysis !== null,
            'latest_analysis' => $analysis ? [
                'analysis_id' => $analysis->CVAnalysisID,
                'overall_score' => $analysis->OverallScore !== null
                    ? (float) $analysis->OverallScore
                    : null,
                'model_version' => $analysis->AiModelVersion,
                'skills_count' => $skillsCount,
                'analyzed_at' => $analysis->AnalyzedAt?->toISOString(),
            ] : null,
        ];
    }
}
