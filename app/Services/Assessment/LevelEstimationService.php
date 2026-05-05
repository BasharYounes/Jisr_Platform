<?php

namespace App\Services\Assessment;

class LevelEstimationService
{
    public function updateLevel(
        float $currentLevel,
        float $normalizedScore
    ): float {
        $newLevel = $currentLevel;

        if ($normalizedScore >= 0.80) {
            $newLevel += 0.5;
        } elseif ($normalizedScore < 0.50) {
            $newLevel -= 0.5;
        }

        return $this->clampLevel($newLevel);
    }

    public function resolveFinalLevel(array $scores, float $startingLevel): float
    {
        $level = $startingLevel;

        foreach ($scores as $score) {
            $level = $this->updateLevel($level, (float) $score);
        }

        return $this->clampLevel($level);
    }

    public function calculateConfidence(int $questionCount, array $scores): float
    {
        if ($questionCount <= 0 || empty($scores)) {
            return 0.00;
        }

        $avgScore = array_sum($scores) / count($scores);

        // صيغة بسيطة للـ MVP
        // كلما زاد عدد الأسئلة وتحسن متوسط الأداء زادت الثقة
        $confidence = min(1, (($questionCount / 3) * 0.5) + ($avgScore * 0.5));

        return round($confidence, 2);
    }

    private function clampLevel(float $level): float
    {
        $level = max(0.0, min(5.0, $level));

        return round($level, 1);
    }
}
