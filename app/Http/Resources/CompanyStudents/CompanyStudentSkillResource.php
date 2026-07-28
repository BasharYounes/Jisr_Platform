<?php

namespace App\Http\Resources\CompanyStudents;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyStudentSkillResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'name' => $this->name,

            'category' => $this->category,

            'proficiency_level' => $this->pivot?->ProficiencyLevel !== null
                    ? (int) $this->pivot->ProficiencyLevel
                    : null,

            'confidence_score' => $this->pivot?->ConfidenceScore !== null
                    ? (float) $this->pivot->ConfidenceScore
                    : null,

            'source' => $this->pivot?->Source,

            'verified' => (bool) (
                $this->pivot?->Verified ?? false
            ),
        ];
    }
}
