<?php

namespace App\Domains\Supervisor\Actions;

use App\Domains\Supervisor\Enums\EvaluationRevisionRequestSource;
use App\Domains\Supervisor\Enums\EvaluationRevisionRequestStatus;
use App\Domains\Supervisor\Enums\ProjectEvaluationAppealDecision;
use App\Domains\Supervisor\Enums\ProjectEvaluationAppealStatus;
use App\Domains\Supervisor\Enums\ProjectEvaluationStatus;
use App\Models\EvaluationRevisionRequest;
use App\Models\ProjectEvaluation;
use App\Models\ProjectEvaluationAppeal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewProjectEvaluationAppealAction
{
    public function execute(
        ProjectEvaluationAppeal $appeal,
        User $reviewedBy,
        ProjectEvaluationAppealDecision $decision,
        string $reviewNotes
    ): array {
        return DB::transaction(function () use (
            $appeal,
            $reviewedBy,
            $decision,
            $reviewNotes
        ): array {
            $lockedAppeal =
                ProjectEvaluationAppeal::query()
                    ->whereKey($appeal->id)
                    ->lockForUpdate()
                    ->firstOrFail();

            if (
                $lockedAppeal->status
                !== ProjectEvaluationAppealStatus::Pending
            ) {
                throw ValidationException::withMessages([
                    'status' => [
                        'Only pending appeals can be reviewed.',
                    ],
                ]);
            }

            $lockedEvaluation =
                ProjectEvaluation::query()
                    ->whereKey(
                        $lockedAppeal
                            ->project_evaluation_id
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

            $revisionRequest = null;

            if (
                $decision
                === ProjectEvaluationAppealDecision::Accept
            ) {
                $revisionRequest =
                    $this->acceptAppeal(
                        appeal: $lockedAppeal,
                        evaluation: $lockedEvaluation,
                        reviewedBy: $reviewedBy,
                    );

                $lockedAppeal->update([
                    'status' =>
                        ProjectEvaluationAppealStatus::Accepted->value,

                    'reviewed_by' => $reviewedBy->id,

                    'review_notes' =>
                        trim($reviewNotes),

                    'reviewed_at' => now(),

                    'revision_request_id' =>
                        $revisionRequest->id,
                ]);
            } else {
                $lockedAppeal->update([
                    'status' =>
                        ProjectEvaluationAppealStatus::Rejected->value,

                    'reviewed_by' => $reviewedBy->id,

                    'review_notes' =>
                        trim($reviewNotes),

                    'reviewed_at' => now(),

                    'revision_request_id' => null,
                ]);
            }

            return [
                'appeal' =>
                    $lockedAppeal
                        ->refresh()
                        ->load([
                            'student:id,name,email',
                            'reviewedBy:id,name,email',
                            'revisionRequest.requestedBy:id,name,email',
                            'revisionRequest.assignedTo:id,name,email',

                            'evaluation.assignment.projectTemplate',
                            'evaluation.student',
                            'evaluation.supervisor',
                            'evaluation.items.criteria',
                            'evaluation.items.evidences',
                        ]),

                'revision_request' =>
                    $revisionRequest
                        ?->refresh()
                        ->load([
                            'requestedBy:id,name,email',
                            'assignedTo:id,name,email',
                        ]),
            ];
        });
    }

    private function acceptAppeal(
        ProjectEvaluationAppeal $appeal,
        ProjectEvaluation $evaluation,
        User $reviewedBy
    ): EvaluationRevisionRequest {
        $allowedStatuses = [
            ProjectEvaluationStatus::SUBMITTED->value,
            ProjectEvaluationStatus::NEEDS_REVISION->value,
        ];

        if (
            ! in_array(
                $evaluation->status,
                $allowedStatuses,
                true
            )
        ) {
            throw ValidationException::withMessages([
                'evaluation' => [
                    'The appeal cannot be accepted for the current evaluation status.',
                ],
            ]);
        }

        /*
         * إذا كان هناك طلب تعديل مفتوح بسبب اعتراض آخر
         * أو بسبب مراجعة المشرف الرئيسي، نعيد استخدامه.
         *
         * لا ننشئ عدة طلبات pending للمشرف نفسه.
         */
        $revisionRequest =
            EvaluationRevisionRequest::query()
                ->where(
                    'project_evaluation_id',
                    $evaluation->id
                )
                ->where(
                    'status',
                    EvaluationRevisionRequestStatus::Pending->value
                )
                ->lockForUpdate()
                ->latest('id')
                ->first();

        if ($revisionRequest === null) {
            $revisionRequest =
                EvaluationRevisionRequest::create([
                    'project_evaluation_id' =>
                        $evaluation->id,

                    'requested_by' =>
                        $reviewedBy->id,

                    'assigned_to' =>
                        $evaluation->supervisor_id,

                    'source' =>
                        EvaluationRevisionRequestSource::StudentAppeal->value,

                    'source_reference_id' =>
                        $appeal->id,

                    'reason' =>
                        'Accepted student appeal #'
                        . $appeal->id
                        . ': '
                        . $appeal->reason,

                    'status' =>
                        EvaluationRevisionRequestStatus::Pending->value,
                ]);
        }

        /*
         * إذا كان التقييم submitted نعيده للمشرف.
         * وإذا كان needs_revision أصلًا نبقيه كما هو.
         */
        if (
            $evaluation->status
            === ProjectEvaluationStatus::SUBMITTED->value
        ) {
            $evaluation->update([
                'status' =>
                    ProjectEvaluationStatus::NEEDS_REVISION->value,
            ]);
        }

        return $revisionRequest;
    }
}
