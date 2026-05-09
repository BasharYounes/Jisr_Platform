<?php

namespace App\Services\Assessment;

class LevelEstimationService
{
    private const MIN_LEVEL = 1.0;
    private const MAX_LEVEL = 5.0;

    private const SIGMOID_SCALE = 1.15;

    public function resolveNextLevel(float $currentLevel, float $normalizedScore): float
    {
        $score = $this->clampScore($normalizedScore);

        $delta = match (true) {
            $score >= 0.90 => 0.45,
            $score >= 0.75 => 0.25,
            $score >= 0.55 => 0.05,
            $score >= 0.40 => -0.20,
            default => -0.45,
        };

        return round($this->clampLevel($currentLevel + $delta), 2);
    }

    public function resolveFinalLevelFromAttempts(array $attempts, float $startingLevel): float
    {
        $attempts = $this->sanitizeAttempts($attempts);

        if (empty($attempts)) {
            return round($this->clampLevel($startingLevel), 2);
        }

        $estimatedLevelByMle = $this->estimateLevelByMle($attempts);

        $scores = array_column($attempts, 'score');

        $stabilityFactor = $this->calculateStabilityFactor($scores);

        $maxAllowedDelta = $this->maxAllowedDelta(
            questionCount: count($attempts),
            stabilityFactor: $stabilityFactor
        );

        $rawDelta = $estimatedLevelByMle - $startingLevel;

        $limitedDelta = max(
            -$maxAllowedDelta,
            min($maxAllowedDelta, $rawDelta)
        );

        return round(
            $this->clampLevel($startingLevel + $limitedDelta),
            2
        );
    }

    public function calculateConfidenceFromAttempts(array $attempts): float
    {
        $attempts = $this->sanitizeAttempts($attempts);

        if (empty($attempts)) {
            return 0.0;
        }

        $scores = array_column($attempts, 'score');
        $levels = array_column($attempts, 'question_level');

        $questionCount = count($attempts);

        $questionFactor = min(1.0, $questionCount / 7);

        $stabilityFactor = $this->calculateStabilityFactor($scores);

        $levelCoverageFactor = $this->calculateLevelCoverageFactor($levels);

        $averageScore = array_sum($scores) / count($scores);

        $performanceClarity = abs($averageScore - 0.50) * 2;

        $confidence =
            (0.35 * $questionFactor)
            + (0.30 * $stabilityFactor)
            + (0.20 * $levelCoverageFactor)
            + (0.15 * $performanceClarity);

        return round(max(0.0, min(1.0, $confidence)), 2);
    }

    public function calculateConfidence(int $questionCount, array $scores): float
    {
        $scores = $this->sanitizeScores($scores);

        if ($questionCount <= 0 || empty($scores)) {
            return 0.0;
        }

        $questionFactor = min(1.0, $questionCount / 7);

        $stabilityFactor = $this->calculateStabilityFactor($scores);

        $averageScore = array_sum($scores) / count($scores);

        $performanceClarity = abs($averageScore - 0.50) * 2;

        $confidence =
            (0.40 * $questionFactor)
            + (0.35 * $stabilityFactor)
            + (0.25 * $performanceClarity);

        return round(max(0.0, min(1.0, $confidence)), 2);
    }

    private function estimateLevelByMle(array $attempts): float
    {
        $bestLevel = self::MIN_LEVEL;
        $bestLogLikelihood = null;

        for ($level = self::MIN_LEVEL; $level <= self::MAX_LEVEL; $level += 0.05) {
            $logLikelihood = 0.0;

            foreach ($attempts as $attempt) {
                $questionLevel = $attempt['question_level'];
                $score = $attempt['score'];
                $difficultyWeight = $attempt['difficulty_weight'];

                $expectedScore = $this->expectedScore(
                    studentLevel: $level,
                    questionLevel: $questionLevel,
                    difficultyWeight: $difficultyWeight
                );

                $logLikelihood += $this->logLikelihood(
                    actualScore: $score,
                    expectedScore: $expectedScore,
                    difficultyWeight: $difficultyWeight
                );
            }

            if ($bestLogLikelihood === null || $logLikelihood > $bestLogLikelihood) {
                $bestLogLikelihood = $logLikelihood;
                $bestLevel = $level;
            }
        }

        return round($this->clampLevel($bestLevel), 2);
    }

    private function expectedScore(
        float $studentLevel,
        float $questionLevel,
        float $difficultyWeight
    ): float {
        $difficultyWeight = max(0.75, min(2.50, $difficultyWeight));

        $levelDifference = $studentLevel - $questionLevel;

        $x = ($levelDifference / self::SIGMOID_SCALE) / $difficultyWeight;

        return 1 / (1 + exp(-$x));
    }

    private function logLikelihood(
        float $actualScore,
        float $expectedScore,
        float $difficultyWeight
    ): float {
        $epsilon = 0.0001;

        $expectedScore = max($epsilon, min(1 - $epsilon, $expectedScore));

        $error = $actualScore - $expectedScore;

        return -1 * ($error ** 2) * max(0.75, min(2.50, $difficultyWeight));
    }

    private function maxAllowedDelta(
        int $questionCount,
        float $stabilityFactor
    ): float {
        $base = match (true) {
            $questionCount <= 3 => 0.85,
            $questionCount <= 5 => 1.10,
            default => 1.35,
        };

        return round($base * max(0.65, min(1.0, $stabilityFactor)), 2);
    }

    private function calculateStabilityFactor(array $scores): float
    {
        $scores = $this->sanitizeScores($scores);

        if (count($scores) < 2) {
            return 0.60;
        }

        $average = array_sum($scores) / count($scores);

        $variance = array_sum(array_map(
            fn (float $score) => ($score - $average) ** 2,
            $scores
        )) / count($scores);

        $standardDeviation = sqrt($variance);

        return round(max(0.50, min(1.0, 1 - $standardDeviation)), 2);
    }

    private function calculateLevelCoverageFactor(array $levels): float
    {
        $levels = array_unique(array_map('intval', $levels));

        return round(min(1.0, count($levels) / 3), 2);
    }

    private function sanitizeAttempts(array $attempts): array
    {
        return array_values(array_filter(array_map(function ($attempt) {
            $score = $attempt['score'] ?? null;
            $questionLevel = $attempt['question_level'] ?? null;
            $difficultyWeight = $attempt['difficulty_weight'] ?? 1.0;

            if (! is_numeric($score) || ! is_numeric($questionLevel)) {
                return null;
            }

            return [
                'score' => $this->clampScore((float) $score),
                'question_level' => (float) max(1, min(5, $questionLevel)),
                'difficulty_weight' => (float) max(0.75, min(2.50, $difficultyWeight)),
            ];
        }, $attempts)));
    }

    private function sanitizeScores(array $scores): array
    {
        return array_values(array_filter(
            array_map(
                fn ($score) => is_numeric($score)
                    ? $this->clampScore((float) $score)
                    : null,
                $scores
            ),
            fn ($score) => $score !== null
        ));
    }

    private function clampScore(float $score): float
    {
        return max(0.0, min(1.0, $score));
    }

    private function clampLevel(float $level): float
    {
        return max(self::MIN_LEVEL, min(self::MAX_LEVEL, $level));
    }
}
