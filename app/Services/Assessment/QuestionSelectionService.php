<?php

namespace App\Services\Assessment;

use App\Models\AssessmentSkillSession;
use App\Models\QuestionBank;

class QuestionSelectionService
{
    public function selectNextQuestion(AssessmentSkillSession $skillSession): ?QuestionBank
    {
        $usedQuestionIds = $skillSession->attempts()
            ->pluck('QuestionID')
            ->toArray();

        $targetLevel = $this->resolveTargetLevel((float) $skillSession->CurrentEstimatedLevel);

        // 1) Try exact level first
        $question = $this->queryBase($skillSession, $usedQuestionIds)
            ->where('Level', $targetLevel)
            ->inRandomOrder()
            ->first();

        if ($question) {
            return $question;
        }

        // 2) Fallback: nearby levels
        $fallbackLevels = $this->fallbackLevels($targetLevel);

        foreach ($fallbackLevels as $level) {
            $question = $this->queryBase($skillSession, $usedQuestionIds)
                ->where('Level', $level)
                ->inRandomOrder()
                ->first();

            if ($question) {
                return $question;
            }
        }

        return null;
    }

    private function queryBase(AssessmentSkillSession $skillSession, array $usedQuestionIds)
    {
        $careerPathId = $skillSession->assessmentSession->CareerPathID;

        return QuestionBank::query()
            ->where('SkillID', $skillSession->SkillID)
            ->where('IsActive', true)
            ->where(function ($query) use ($careerPathId) {
                $query->where('CareerPathID', $careerPathId)
                    ->orWhereNull('CareerPathID');
            })
            ->when(!empty($usedQuestionIds), function ($query) use ($usedQuestionIds) {
                $query->whereNotIn('QuestionID', $usedQuestionIds);
            });
    }

    private function resolveTargetLevel(float $currentEstimatedLevel): int
    {
        $rounded = (int) round($currentEstimatedLevel);

        return max(1, min(5, $rounded));
    }

    private function fallbackLevels(int $targetLevel): array
    {
        $levels = [1, 2, 3, 4, 5];

        usort($levels, function ($a, $b) use ($targetLevel) {
            return abs($a - $targetLevel) <=> abs($b - $targetLevel);
        });

        return array_values(array_filter($levels, fn ($level) => $level !== $targetLevel));
    }
}
