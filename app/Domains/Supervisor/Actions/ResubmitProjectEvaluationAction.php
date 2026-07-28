<?php

namespace App\Domains\Supervisor\Actions;

use App\Domains\Supervisor\Enums\EvaluationRevisionRequestStatus;
use App\Domains\Supervisor\Enums\ProjectEvaluationStatus;
use App\Models\EvaluationRevisionRequest;
use App\Models\ProjectEvaluation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResubmitProjectEvaluationAction
{
    public function __construct(
        private readonly RecalculateProjectEvaluationScoreAction $recalculateScore
    ) {}

    public function execute(
        ProjectEvaluation $evaluation,
        User $supervisor,
        ?string $resolutionNote = null
    ): array {
        return DB::transaction(function () use (
            $evaluation,
            $supervisor,
            $resolutionNote
        ): array {
            $lockedEvaluation =
                ProjectEvaluation::query()
                    ->whereKey($evaluation->id)
                    ->lockForUpdate()
                    ->firstOrFail();

            if (
                (int) $lockedEvaluation->supervisor_id
                !== (int) $supervisor->id
            ) {
                throw ValidationException::withMessages([
                    'supervisor' => [
                        'Only the supervisor who created the evaluation can resubmit it.',
                    ],
                ]);
            }

            if (
                $lockedEvaluation->status
                !== ProjectEvaluationStatus::NEEDS_REVISION->value
            ) {
                throw ValidationException::withMessages([
                    'status' => [
                        'Only needs_revision evaluations can be resubmitted.',
                    ],
                ]);
            }

            $revisionRequest =
                EvaluationRevisionRequest::query()
                    ->where(
                        'project_evaluation_id',
                        $lockedEvaluation->id
                    )
                    ->where(
                        'assigned_to',
                        $supervisor->id
                    )
                    ->where(
                        'status',
                        EvaluationRevisionRequestStatus::Pending->value
                    )
                    ->lockForUpdate()
                    ->latest('id')
                    ->first();

            if ($revisionRequest === null) {
                throw ValidationException::withMessages([
                    'revision_request' => [
                        'No pending revision request was found for this evaluation.',
                    ],
                ]);
            }

            /*
             * فحص نهائي وإعادة حساب قبل الإرسال.
             */
            $this->recalculateScore->execute(
                $lockedEvaluation
            );

            $lockedEvaluation->update([
                'status' => ProjectEvaluationStatus::SUBMITTED->value,

                'evaluated_at' => now(),
            ]);

            $lockedEvaluation
                ->initializeAppealWindowIfMissing();

            $revisionRequest->update([
                'status' => EvaluationRevisionRequestStatus::Resolved->value,

                'resolution_note' => $resolutionNote !== null
                        ? trim($resolutionNote)
                        : 'Evaluation updated and resubmitted.',

                'resolved_at' => now(),
            ]);

            /*
             * لاحقًا عند إضافة نافذة الاعتراض:
             * لن نعيد ضبط موعد الـ48 ساعة هنا.
             */
            return [
                'evaluation' => $lockedEvaluation
                    ->refresh()
                    ->load([
                        'assignment.projectTemplate',
                        'student',
                        'supervisor',
                        'items.criteria',
                        'items.evidences',
                    ]),

                'revision_request' => $revisionRequest
                    ->refresh()
                    ->load([
                        'requestedBy:id,name,email',
                        'assignedTo:id,name,email',
                    ]),
            ];
        });
    }
}
