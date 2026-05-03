<?php

namespace App\Services\Recommendations;

use App\Models\LearningResource;

class LearningRecommendationService
{
    public function recommend(int $skillId, float $currentLevel, float $targetLevel): array
    {
        $neededLevel = ceil($currentLevel + 1);

        return LearningResource::query()
            ->where('SkillID', $skillId)
            ->where('IsActive', true)
            ->whereBetween('Level', [$neededLevel, $targetLevel])
            ->orderBy('Level')
            ->limit(5)
            ->get()
            ->map(function ($res) {
                return [
                    'title' => $res->Title,
                    'url' => $res->Url,
                    'type' => $res->Type,
                    'level' => $res->Level,
                    'estimated_hours' => $res->EstimatedHours,
                    'provider' => $res->Provider,
                ];
            })
            ->toArray();
    }
}
