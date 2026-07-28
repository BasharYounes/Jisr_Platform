<?php

namespace App\Domains\Supervisor\Actions;

use App\Domains\Supervisor\Enums\ProjectEvaluationStatus;
use App\Models\ProjectEvaluation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateProjectEvaluationAction
{
    public function __construct(
        private readonly
        RecalculateProjectEvaluationScoreAction
        $recalculateScore
    ) {}

    public function execute(
        ProjectEvaluation $evaluation,
        array $data
    ): ProjectEvaluation {
        return DB::transaction(function () use (
            $evaluation,
            $data
        ): ProjectEvaluation {
            $lockedEvaluation =
                ProjectEvaluation::query()
                    ->whereKey($evaluation->id)
                    ->lockForUpdate()
                    ->firstOrFail();

            $allowedStatuses = [
                ProjectEvaluationStatus::DRAFT->value,
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
                        'Only draft or needs_revision evaluations can be modified.',
                    ],
                ]);
            }

            if (
                array_key_exists(
                    'general_comment',
                    $data
                )
            ) {
                $lockedEvaluation->general_comment =
                    $data['general_comment'];
            }

            if (array_key_exists('items', $data)) {
                $payloadItems = collect(
                    $data['items']
                )->keyBy('id');

                /*
                 * نجلب فقط العناصر التابعة لهذا التقييم.
                 * وجود العنصر في قاعدة البيانات وحده لا يكفي.
                 */
                $evaluationItems =
                    $lockedEvaluation
                        ->items()
                        ->with('criteria')
                        ->whereIn(
                            'id',
                            $payloadItems->keys()
                        )
                        ->lockForUpdate()
                        ->get()
                        ->keyBy('id');

                if (
                    $evaluationItems->count()
                    !== $payloadItems->count()
                ) {
                    throw ValidationException::withMessages([
                        'items' => [
                            'One or more evaluation items do not belong to this evaluation.',
                        ],
                    ]);
                }

                foreach (
                    $payloadItems as
                    $itemId => $itemData
                ) {
                    $evaluationItem =
                        $evaluationItems->get(
                            $itemId
                        );

                    $criterion =
                        $evaluationItem->criteria;

                    if ($criterion === null) {
                        throw ValidationException::withMessages([
                            'items' => [
                                "Evaluation item {$itemId} has no valid criterion.",
                            ],
                        ]);
                    }

                    $score = (float) $itemData['score'];
                    $maxScore =
                        (float) $criterion->max_score;

                    if ($score > $maxScore) {
                        throw ValidationException::withMessages([
                            'items' => [
                                "Score cannot exceed {$maxScore} for criterion {$criterion->name}.",
                            ],
                        ]);
                    }

                    $updates = [
                        'score' => $score,
                    ];

                    if (
                        array_key_exists(
                            'comment',
                            $itemData
                        )
                    ) {
                        $updates['comment'] =
                            $itemData['comment'];
                    }

                    if (
                        array_key_exists(
                            'evidence',
                            $itemData
                        )
                    ) {
                        $updates['evidence'] =
                            $itemData['evidence'];
                    }

                    /*
                     * لا نحذف العنصر ولا صور الإثبات.
                     * نحدث البيانات الموجودة في مكانها.
                     */
                    $evaluationItem->update(
                        $updates
                    );
                }
            }

            $lockedEvaluation->save();

            /*
             * نعيد الحساب فقط عندما تغيرت العناصر.
             * تعديل التعليق وحده لا يغير الدرجة.
             */
            if (array_key_exists('items', $data)) {
                $this->recalculateScore->execute(
                    $lockedEvaluation
                );
            }

            return $lockedEvaluation
                ->refresh()
                ->load([
                    'assignment.projectTemplate',
                    'student',
                    'supervisor',
                    'items.criteria',
                    'items.evidences',
                    'pendingRevisionRequest.requestedBy',
                    'pendingRevisionRequest.assignedTo',
                ]);
        });
    }
}
