<?php

namespace App\Domains\Supervisor\Actions;

use App\Domains\Supervisor\Enums\ProjectAssignmentStatus;
use App\Domains\Supervisor\Enums\ProjectAssignmentTaskStatus;
use App\Domains\Supervisor\Enums\ProjectEvaluationStatus;
use App\Models\EvaluationCriteria;
use App\Models\ProjectAssignment;
use App\Models\ProjectEvaluation;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SubmitProjectEvaluationAction
{
    public function execute(
        ProjectAssignment $assignment,
        array $data
    ): ProjectEvaluation {
        return DB::transaction(function () use ($assignment, $data) {
            $criteria = EvaluationCriteria::query()
                ->whereIn(
                    'id',
                    collect($data['items'])->pluck('evaluation_criteria_id')
                )
                ->get()
                ->keyBy('id');

            $allowedStatuses = [
                ProjectAssignmentStatus::UNDER_REVIEW,
            ];

            if (! in_array($assignment->status, $allowedStatuses, true)) {
                throw new DomainException(
                    'Project can only be evaluated when it is submitted or under review.'
                );
            }

            $totalTasks = $assignment->assignmentTasks()->count();

            if ($totalTasks === 0) {
                throw new DomainException(
                    'This project assignment has no tasks to evaluate.'
                );
            }

            $unfinishedTasks = $assignment->assignmentTasks()
                ->where('status', '!=', ProjectAssignmentTaskStatus::DONE->value)
                ->count();

            if ($unfinishedTasks > 0) {
                throw new DomainException(
                    'Final evaluation is allowed only after all assignment tasks are completed.'
                );
            }

            $totalWeightedScore = 0;
            $totalWeights = 0;

            foreach ($data['items'] as $item) {
                $criterion = $criteria->get($item['evaluation_criteria_id']);

                if (! $criterion) {
                    throw new InvalidArgumentException('Invalid evaluation criterion.');
                }

                if ($item['score'] > $criterion->max_score) {
                    throw new InvalidArgumentException(
                        "Score cannot exceed max score for criterion: {$criterion->name}"
                    );
                }

                $normalizedScore = $item['score'] / $criterion->max_score;

                $totalWeightedScore += $normalizedScore * $criterion->weight;
                $totalWeights += $criterion->weight;
            }

            $finalGrade = $totalWeights > 0
                ? round(($totalWeightedScore / $totalWeights) * 100, 2)
                : 0;

            $evaluation = ProjectEvaluation::updateOrCreate(
                [
                    'project_assignment_id' => $assignment->id,
                ],
                [
                    'supervisor_id' => auth()->id(),
                    'total_score' => round($totalWeightedScore, 2),
                    'final_grade' => $finalGrade,
                    'status' => ProjectEvaluationStatus::SUBMITTED->value,
                    'general_comment' => $data['general_comment'] ?? null,
                    'summary_metrics' => [
                        'criteria_count' => count($data['items']),
                        'total_weight' => $totalWeights,
                        'calculated_at' => now()->toISOString(),
                    ],
                    'evaluated_at' => now(),
                ]
            );

            $evaluation->items()->delete();

            foreach ($data['items'] as $item) {
                $evaluation->items()->create([
                    'evaluation_criteria_id' => $item['evaluation_criteria_id'],
                    'score' => $item['score'],
                    'comment' => $item['comment'] ?? null,
                    'evidence' => $item['evidence'] ?? null,
                    'evidence_urls' => $item['evidence_urls'] ?? null,
                ]);
            }

            return $evaluation->load([
                'assignment.projectTemplate',
                'assignment.members.student',
                'supervisor',
                'items.criteria',
            ]);
        });
    }
}
