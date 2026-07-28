<?php

namespace App\Domains\Supervisor\Actions;

use App\Models\ProjectEvaluation;
use Illuminate\Validation\ValidationException;

class RecalculateProjectEvaluationScoreAction
{
    public function execute(
        ProjectEvaluation $evaluation
    ): ProjectEvaluation {
        $items = $evaluation
            ->items()
            ->with('criteria')
            ->get();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => [
                    'The evaluation must contain at least one evaluation item.',
                ],
            ]);
        }

        $totalWeightedScore = 0.0;
        $totalWeights = 0.0;

        foreach ($items as $item) {
            $criterion = $item->criteria;

            if ($criterion === null) {
                throw ValidationException::withMessages([
                    'items' => [
                        "The evaluation item {$item->id} has no valid criterion.",
                    ],
                ]);
            }

            $maxScore = (float) $criterion->max_score;
            $weight = (float) $criterion->weight;
            $score = (float) $item->score;

            if ($maxScore <= 0) {
                throw ValidationException::withMessages([
                    'items' => [
                        "The maximum score for criterion {$criterion->name} must be greater than zero.",
                    ],
                ]);
            }

            if ($score < 0 || $score > $maxScore) {
                throw ValidationException::withMessages([
                    'items' => [
                        "The score for criterion {$criterion->name} must be between 0 and {$maxScore}.",
                    ],
                ]);
            }

            $normalizedScore = $score / $maxScore;

            $totalWeightedScore +=
                $normalizedScore * $weight;

            $totalWeights += $weight;
        }

        $finalGrade = $totalWeights > 0
            ? round(
                ($totalWeightedScore / $totalWeights) * 100,
                2
            )
            : 0;

        $oldMetrics = is_array(
            $evaluation->summary_metrics
        )
            ? $evaluation->summary_metrics
            : [];

        $evaluation->forceFill([
            'total_score' => round(
                $totalWeightedScore,
                2
            ),

            'final_grade' => $finalGrade,

            'summary_metrics' => array_merge(
                $oldMetrics,
                [
                    'criteria_count' => $items->count(),
                    'total_weight' => round(
                        $totalWeights,
                        2
                    ),
                    'recalculated_at' => now()->toISOString(),
                ]
            ),
        ])->save();

        return $evaluation;
    }
}
