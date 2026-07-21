<?php

namespace App\Domains\Student\Actions;

use App\Domains\Supervisor\Enums\ProjectEvaluationAppealStatus;
use App\Domains\Supervisor\Enums\ProjectEvaluationStatus;
use App\Models\ProjectEvaluation;
use App\Models\ProjectEvaluationAppeal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateProjectEvaluationAppealAction
{
    public function execute(
        ProjectEvaluation $evaluation,
        User $student,
        string $reason
    ): ProjectEvaluationAppeal {
        return DB::transaction(function () use (
            $evaluation,
            $student,
            $reason
        ): ProjectEvaluationAppeal {
            $lockedEvaluation =
                ProjectEvaluation::query()
                    ->whereKey($evaluation->id)
                    ->lockForUpdate()
                    ->firstOrFail();

            if (
                (int) $lockedEvaluation->student_id
                !== (int) $student->id
            ) {
                throw ValidationException::withMessages([
                    'evaluation' => [
                        'This evaluation does not belong to the authenticated student.',
                    ],
                ]);
            }

            $allowedStatuses = [
                ProjectEvaluationStatus::SUBMITTED->value,
                ProjectEvaluationStatus::NEEDS_REVISION->value,
            ];

            if (
                ! in_array(
                    $lockedEvaluation->status,
                    $allowedStatuses,
                    true
                )
            ) {
                throw ValidationException::withMessages([
                    'status' => [
                        'An appeal can be submitted only for a submitted or needs_revision evaluation.',
                    ],
                ]);
            }

            if (
                $lockedEvaluation->appeal_started_at === null
                || $lockedEvaluation->appeal_deadline_at === null
            ) {
                throw ValidationException::withMessages([
                    'appeal_window' => [
                        'The appeal window has not started for this evaluation.',
                    ],
                ]);
            }

            if (
                now()->greaterThan(
                    $lockedEvaluation->appeal_deadline_at
                )
            ) {
                throw ValidationException::withMessages([
                    'appeal_window' => [
                        'The 48-hour appeal window has expired.',
                    ],
                ]);
            }

            $lockedEvaluation->load([
                'supervisor:id,name,email',
                'items.criteria',
            ]);

            $snapshot = [
                'captured_at' => now()->toISOString(),

                'evaluation' => [
                    'id' => $lockedEvaluation->id,

                    'project_assignment_id' =>
                        $lockedEvaluation
                            ->project_assignment_id,

                    'student_id' =>
                        $lockedEvaluation->student_id,

                    'supervisor' => [
                        'id' =>
                            $lockedEvaluation
                                ->supervisor
                                ?->id,

                        'name' =>
                            $lockedEvaluation
                                ->supervisor
                                ?->name,

                        'email' =>
                            $lockedEvaluation
                                ->supervisor
                                ?->email,
                    ],

                    'status' =>
                        $lockedEvaluation->status,

                    'total_score' =>
                        $lockedEvaluation->total_score,

                    'final_grade' =>
                        $lockedEvaluation->final_grade,

                    'general_comment' =>
                        $lockedEvaluation->general_comment,

                    'summary_metrics' =>
                        $lockedEvaluation->summary_metrics,

                    'evaluated_at' =>
                        $lockedEvaluation
                            ->evaluated_at
                            ?->toISOString(),

                    'appeal_started_at' =>
                        $lockedEvaluation
                            ->appeal_started_at
                            ?->toISOString(),

                    'appeal_deadline_at' =>
                        $lockedEvaluation
                            ->appeal_deadline_at
                            ?->toISOString(),
                ],

                'items' => $lockedEvaluation
                    ->items
                    ->map(function ($item): array {
                        $criterion = $item->criteria;

                        return [
                            'id' => $item->id,
                            'score' => $item->score,
                            'comment' => $item->comment,
                            'evidence' => $item->evidence,

                            'criteria' => $criterion
                                ? [
                                    'id' => $criterion->id,
                                    'name' => $criterion->name,
                                    'description' =>
                                        $criterion->description,
                                    'category' =>
                                        $criterion->category,
                                    'max_score' =>
                                        $criterion->max_score,
                                    'weight' =>
                                        $criterion->weight,
                                    'scoring_anchors' =>
                                        $criterion
                                            ->scoring_anchors,
                                ]
                                : null,
                        ];
                    })
                    ->values()
                    ->all(),
            ];

            return ProjectEvaluationAppeal::create([
                'project_evaluation_id' =>
                    $lockedEvaluation->id,

                'student_id' => $student->id,

                'reason' => trim($reason),

                'status' =>
                    ProjectEvaluationAppealStatus::Pending->value,

                'evaluation_snapshot' => $snapshot,
            ]);
        });
    }
}
