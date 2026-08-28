<?php

namespace App\Http\Resources\Student;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentCvAnalysisResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $skills = $this->relationLoaded('extractedSkills')
            ? $this->extractedSkills->map(fn ($item) => [
                'extracted_skill_id' => $item->CVExtractedSkillID,
                'skill_id' => $item->SkillID,
                'skill_name' => $item->skill?->name,
                'raw_skill_name' => $item->RawSkillName,
                'evidence' => $item->EvidenceText,
                'initial_level' => (float) $item->InitialLevel,
                'confidence_score' => (float) $item->ConfidenceScore,
                'extraction_source' => $item->ExtractionSource,
            ])->values()
            : collect();

        if ($skills->isEmpty()) {
            $skills = collect($this->ExtractedSkillsJson ?? [])
                ->map(fn (array $item) => [
                    'extracted_skill_id' => null,
                    'skill_id' => $item['skill_id'] ?? null,
                    'skill_name' => $item['skill_name'] ?? null,
                    'raw_skill_name' => $item['skill_name'] ?? null,
                    'evidence' => $item['evidence'] ?? null,
                    'initial_level' => isset($item['initial_level'])
                        ? (float) $item['initial_level']
                        : null,
                    'confidence_score' => isset($item['confidence'])
                        ? (float) $item['confidence']
                        : null,
                    'extraction_source' => 'analysis_json',
                ])->values();
        }

        return [
            'analysis_id' => $this->CVAnalysisID,
            'cv_id' => $this->CvId,
            'overall_score' => $this->OverallScore !== null
                ? (float) $this->OverallScore
                : null,
            'model_version' => $this->AiModelVersion,
            'analyzed_at' => $this->AnalyzedAt?->toISOString(),
            'missing_criteria' => $this->MissingCriteriaJson ?? [],
            'skills' => $skills,
        ];
    }
}
