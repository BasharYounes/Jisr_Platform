<?php

namespace App\Services\Assessment;

use App\Models\QuestionBank;
use Illuminate\Support\Str;

class EvaluationValidationService
{
    public function validateAndNormalize(QuestionBank $question, array $rawEvaluation): array
    {
        $question->loadMissing('rubrics');

        $warnings = [];

        $rubrics = $question->rubrics
            ->sortBy('OrderIndex')
            ->values();

        if ($rubrics->isEmpty()) {
            return $this->fallbackEvaluation(
                rawEvaluation: $rawEvaluation,
                warnings: ['No rubrics found for this question.']
            );
        }

        $criteriaResults = $rawEvaluation['criteria_results'] ?? [];

        if (! is_array($criteriaResults)) {
            $criteriaResults = [];
            $warnings[] = 'criteria_results is missing or not an array.';
        }

        $criteriaByName = collect($criteriaResults)
            ->filter(fn ($item) => is_array($item))
            ->keyBy(function ($item) {
                return $this->normalizeName($item['criterion_name'] ?? $item['name'] ?? '');
            });

        $validatedCriteria = [];
        $totalScore = 0.0;
        $maxScore = 0.0;

        foreach ($rubrics as $rubric) {
            $criterionName = (string) $rubric->CriterionName;
            $normalizedName = $this->normalizeName($criterionName);

            $maxCriterionScore = (float) $rubric->MaxScore;
            $maxScore += $maxCriterionScore;

            $aiCriterion = $criteriaByName->get($normalizedName);

            if (! $aiCriterion) {
                $warnings[] = "Missing criterion result: {$criterionName}";

                $validatedCriteria[] = [
                    'criterion_name' => $criterionName,
                    'score' => 0.0,
                    'max_score' => $maxCriterionScore,
                    'reason' => 'لم يتم إرجاع تقييم لهذا المعيار من النموذج.',
                    'was_missing' => true,
                ];

                continue;
            }

            $score = $this->toFloat($aiCriterion['score'] ?? 0);

            if ($score < 0) {
                $warnings[] = "Negative score corrected for criterion: {$criterionName}";
                $score = 0.0;
            }

            if ($score > $maxCriterionScore) {
                $warnings[] = "Score exceeded max_score and was clamped for criterion: {$criterionName}";
                $score = $maxCriterionScore;
            }

            $totalScore += $score;

            $validatedCriteria[] = [
                'criterion_name' => $criterionName,
                'score' => round($score, 2),
                'max_score' => round($maxCriterionScore, 2),
                'reason' => $aiCriterion['reason'] ?? $aiCriterion['feedback'] ?? null,
                'was_missing' => false,
            ];
        }

        $normalizedScore = $maxScore > 0
            ? $totalScore / $maxScore
            : 0.0;

        $normalizedScore = max(0.0, min(1.0, $normalizedScore));

        $this->appendScoreMismatchWarnings(
            rawEvaluation: $rawEvaluation,
            serverTotalScore: $totalScore,
            serverMaxScore: $maxScore,
            serverNormalizedScore: $normalizedScore,
            warnings: $warnings
        );

        $needsReview = $this->needsHumanReview(
            rawEvaluation: $rawEvaluation,
            warnings: $warnings,
            normalizedScore: $normalizedScore
        );

        return array_merge($rawEvaluation, [
            'criteria_results' => $validatedCriteria,
            'total_score' => round($totalScore, 2),
            'max_score' => round($maxScore, 2),
            'normalized_score' => round($normalizedScore, 4),

            'validation' => [
                'is_valid' => empty($warnings),
                'needs_review' => $needsReview,
                'warnings' => $warnings,
                'source' => 'server_recalculated',
            ],
        ]);
    }

    private function fallbackEvaluation(array $rawEvaluation, array $warnings): array
    {
        $normalizedScore = $this->toFloat($rawEvaluation['normalized_score'] ?? 0);
        $normalizedScore = max(0.0, min(1.0, $normalizedScore));

        return array_merge($rawEvaluation, [
            'total_score' => $this->toFloat($rawEvaluation['total_score'] ?? 0),
            'normalized_score' => round($normalizedScore, 4),
            'validation' => [
                'is_valid' => false,
                'needs_review' => true,
                'warnings' => $warnings,
                'source' => 'fallback_no_rubrics',
            ],
        ]);
    }

    private function needsHumanReview(array $rawEvaluation, array $warnings, float $normalizedScore): bool
    {
        if (! empty($warnings)) {
            return true;
        }

        $aiConfidence = $rawEvaluation['confidence'] ?? null;

        if (is_numeric($aiConfidence) && (float) $aiConfidence < 0.50) {
            return true;
        }

        if (is_string($aiConfidence) && in_array(strtolower($aiConfidence), ['low', 'منخفضة', 'منخفض'], true)) {
            return true;
        }

        // منطقة رمادية: ليست رسوبًا واضحًا ولا نجاحًا واضحًا
        if ($normalizedScore >= 0.45 && $normalizedScore <= 0.60) {
            return true;
        }

        return false;
    }

    private function normalizeName(string $name): string
    {
        return Str::of($name)
            ->trim()
            ->lower()
            ->replace(['أ', 'إ', 'آ'], 'ا')
            ->replace('ة', 'ه')
            ->replace('ى', 'ي')
            ->replaceMatches('/\s+/', ' ')
            ->toString();
    }

    private function toFloat(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function appendScoreMismatchWarnings(
        array $rawEvaluation,
        float $serverTotalScore,
        float $serverMaxScore,
        float $serverNormalizedScore,
        array &$warnings
    ): void {
        $aiTotalScore = $rawEvaluation['total_score'] ?? null;

        if (
            is_numeric($aiTotalScore)
            && abs((float) $aiTotalScore - $serverTotalScore) > 0.01
        ) {
            $warnings[] = 'AI total_score differed from server-calculated total_score.';
        }

        $aiMaxScore = $rawEvaluation['max_score'] ?? null;

        if (
            is_numeric($aiMaxScore)
            && abs((float) $aiMaxScore - $serverMaxScore) > 0.01
        ) {
            $warnings[] = 'AI max_score differed from server-calculated max_score.';
        }

        $aiNormalizedScore = $rawEvaluation['normalized_score'] ?? null;

        if (
            is_numeric($aiNormalizedScore)
            && abs((float) $aiNormalizedScore - $serverNormalizedScore) > 0.01
        ) {
            $warnings[] = 'AI normalized_score differed from server-calculated normalized_score.';
        }
    }
}
