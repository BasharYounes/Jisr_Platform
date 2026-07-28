<?php

namespace App\Domains\Supervisor\Actions;

use App\Domains\Supervisor\Enums\EvaluationRevisionRequestStatus;
use App\Domains\Supervisor\Enums\ProjectAssignmentStatus;
use App\Domains\Supervisor\Enums\ProjectEvaluationAppealStatus;
use App\Models\AuditLog;
use App\Models\EvaluationRevisionRequest;
use App\Models\ProjectAssignment;
use App\Models\ProjectEvaluation;
use App\Models\ProjectEvaluationAppeal;
use App\Models\User;
use BackedEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChangeProjectAssignmentSupervisorAction
{
    public function execute(
        ProjectAssignment $projectAssignment,
        User $changedBy,
        int $newSupervisorId,
        string $reason
    ): array {
        return DB::transaction(function () use (
            $projectAssignment,
            $changedBy,
            $newSupervisorId,
            $reason
        ): array {
            $lockedAssignment =
                ProjectAssignment::query()
                    ->whereKey($projectAssignment->id)
                    ->lockForUpdate()
                    ->firstOrFail();

            $lockedAssignment->loadMissing([
                'projectTemplate',
                'supervisor.supervisorProfile',
            ]);

            $changedBy->loadMissing(
                'supervisorProfile'
            );

            $newSupervisor =
                User::query()
                    ->with('supervisorProfile')
                    ->findOrFail($newSupervisorId);

            $this->validateAssignmentStatus(
                $lockedAssignment
            );

            $this->validateNewSupervisor(
                assignment: $lockedAssignment,
                changedBy: $changedBy,
                newSupervisor: $newSupervisor,
            );

            $oldSupervisor =
                $lockedAssignment->supervisor;

            /*
             * نقفل جميع تقييمات المشروع قبل أرشفتها
             * ومنع أي تعديل متزامن عليها.
             */
            $evaluations =
                ProjectEvaluation::query()
                    ->where(
                        'project_assignment_id',
                        $lockedAssignment->id
                    )
                    ->with([
                        'student:id,name,email',
                        'supervisor:id,name,email',
                        'items.criteria',
                        'items.evidences',
                        'appeals',
                        'revisionRequests',
                    ])
                    ->lockForUpdate()
                    ->get();

            $evaluationIds =
                $evaluations
                    ->pluck('id')
                    ->values();

            /*
             * نحفظ النسخة الأصلية قبل الإلغاء والحذف.
             */
            $evaluationSnapshots =
                $evaluations
                    ->map(
                        fn (
                            ProjectEvaluation $evaluation
                        ): array =>
                            $this->snapshotEvaluation(
                                $evaluation
                            )
                    )
                    ->values()
                    ->all();

            $pendingAppealIds = collect();
            $pendingRevisionRequestIds = collect();

            if ($evaluationIds->isNotEmpty()) {
                $pendingAppealIds =
                    ProjectEvaluationAppeal::query()
                        ->whereIn(
                            'project_evaluation_id',
                            $evaluationIds
                        )
                        ->where(
                            'status',
                            ProjectEvaluationAppealStatus::Pending->value
                        )
                        ->lockForUpdate()
                        ->pluck('id');

                $pendingRevisionRequestIds =
                    EvaluationRevisionRequest::query()
                        ->whereIn(
                            'project_evaluation_id',
                            $evaluationIds
                        )
                        ->where(
                            'status',
                            EvaluationRevisionRequestStatus::Pending->value
                        )
                        ->lockForUpdate()
                        ->pluck('id');

                /*
                 * تسجيل الإلغاء قبل إزالة التقييمات
                 * من سير العمل.
                 */
                ProjectEvaluationAppeal::query()
                    ->whereIn(
                        'id',
                        $pendingAppealIds
                    )
                    ->update([
                        'status' =>
                            ProjectEvaluationAppealStatus::Cancelled->value,

                        'reviewed_by' =>
                            $changedBy->id,

                        'review_notes' =>
                            'Cancelled because the project supervisor was changed. Reason: '
                            . trim($reason),

                        'reviewed_at' => now(),
                        'updated_at' => now(),
                    ]);

                EvaluationRevisionRequest::query()
                    ->whereIn(
                        'id',
                        $pendingRevisionRequestIds
                    )
                    ->update([
                        'status' =>
                            EvaluationRevisionRequestStatus::Cancelled->value,

                        'resolution_note' =>
                            'Cancelled because the project supervisor was changed. Reason: '
                            . trim($reason),

                        'resolved_at' => now(),
                        'updated_at' => now(),
                    ]);
            }

            /*
             * نحذف التقييمات القديمة من سير العمل.
             * العلاقات التابعة لها تُحذف بواسطة
             * Foreign-Key Cascades.
             *
             * النسخة الكاملة محفوظة في Audit Log.
             */
            $deletedEvaluationsCount = 0;

            if ($evaluationIds->isNotEmpty()) {
                $deletedEvaluationsCount =
                    ProjectEvaluation::query()
                        ->whereIn(
                            'id',
                            $evaluationIds
                        )
                        ->delete();
            }

            $lockedAssignment->update([
                'supervisor_id' =>
                    $newSupervisor->id,
            ]);

            AuditLog::create([
                'user_id' => $changedBy->id,

                'action' =>
                    'project_assignment_supervisor_changed',

                'entity_type' =>
                    ProjectAssignment::class,

                'entity_id' =>
                    $lockedAssignment->id,

                'old_value' => [
                    'assignment_status' =>
                        $this->enumValue(
                            $lockedAssignment->status
                        ),

                    'supervisor' => [
                        'id' =>
                            $oldSupervisor?->id,

                        'name' =>
                            $oldSupervisor?->name,

                        'email' =>
                            $oldSupervisor?->email,
                    ],

                    /*
                     * يحتوي التقييمات وعناصرها
                     * واعتراضاتها وطلبات تعديلها.
                     */
                    'evaluations' =>
                        $evaluationSnapshots,
                ],

                'new_value' => [
                    'supervisor' => [
                        'id' =>
                            $newSupervisor->id,

                        'name' =>
                            $newSupervisor->name,

                        'email' =>
                            $newSupervisor->email,
                    ],

                    'reason' => trim($reason),

                    'deleted_evaluation_ids' =>
                        $evaluationIds->all(),

                    'cancelled_appeal_ids' =>
                        $pendingAppealIds->all(),

                    'cancelled_revision_request_ids' =>
                        $pendingRevisionRequestIds->all(),

                    'changed_at' =>
                        now()->toISOString(),
                ],
            ]);

            $updatedAssignment =
                $lockedAssignment
                    ->refresh()
                    ->load([
                        'projectTemplate',
                        'supervisor.supervisorProfile',
                    ]);

            return [
                'assignment' => [
                    'id' =>
                        $updatedAssignment->id,

                    'status' =>
                        $this->enumValue(
                            $updatedAssignment->status
                        ),

                    'progress_percentage' =>
                        $updatedAssignment
                            ->progress_percentage,

                    'project_template' => [
                        'id' =>
                            $updatedAssignment
                                ->projectTemplate
                                ?->id,

                        'title' =>
                            $updatedAssignment
                                ->projectTemplate
                                ?->title,
                    ],
                ],

                'old_supervisor' => [
                    'id' =>
                        $oldSupervisor?->id,

                    'name' =>
                        $oldSupervisor?->name,

                    'email' =>
                        $oldSupervisor?->email,
                ],

                'new_supervisor' => [
                    'id' =>
                        $newSupervisor->id,

                    'name' =>
                        $newSupervisor->name,

                    'email' =>
                        $newSupervisor->email,

                    'specialization' =>
                        $this->enumValue(
                            $newSupervisor
                                ->supervisorProfile
                                ?->specialization
                        ),
                ],

                'archived_data' => [
                    'deleted_evaluations_count' =>
                        $deletedEvaluationsCount,

                    'cancelled_appeals_count' =>
                        $pendingAppealIds->count(),

                    'cancelled_revision_requests_count' =>
                        $pendingRevisionRequestIds
                            ->count(),
                ],

                'reason' => trim($reason),
            ];
        });
    }

    private function validateAssignmentStatus(
        ProjectAssignment $assignment
    ): void {
        $allowedStatuses = [
            ProjectAssignmentStatus::PENDING->value,
            ProjectAssignmentStatus::ASSIGNED->value,
            ProjectAssignmentStatus::IN_PROGRESS->value,
            ProjectAssignmentStatus::SUBMITTED->value,
            ProjectAssignmentStatus::UNDER_REVIEW->value,
        ];

        $status =
            $this->enumValue(
                $assignment->status
            );

        if (
            ! in_array(
                $status,
                $allowedStatuses,
                true
            )
        ) {
            throw ValidationException::withMessages([
                'project_assignment' => [
                    'The supervisor cannot be changed for a completed or rejected project.',
                ],
            ]);
        }
    }

    private function validateNewSupervisor(
        ProjectAssignment $assignment,
        User $changedBy,
        User $newSupervisor
    ): void {
        if (
            (int) $newSupervisor->id
            === (int) $assignment->supervisor_id
        ) {
            throw ValidationException::withMessages([
                'new_supervisor_id' => [
                    'The selected user is already the project supervisor.',
                ],
            ]);
        }

        if (
            (int) $newSupervisor->id
            === (int) $changedBy->id
        ) {
            throw ValidationException::withMessages([
                'new_supervisor_id' => [
                    'The supervisor lead cannot assign themselves as the replacement supervisor.',
                ],
            ]);
        }

        if (! $newSupervisor->is_active) {
            throw ValidationException::withMessages([
                'new_supervisor_id' => [
                    'The replacement supervisor must have an active account.',
                ],
            ]);
        }

        if (! $newSupervisor->hasRole('supervisor')) {
            throw ValidationException::withMessages([
                'new_supervisor_id' => [
                    'The selected user must have the supervisor role.',
                ],
            ]);
        }

        if (
            $newSupervisor->supervisorProfile === null
            || $newSupervisor
                ->supervisorProfile
                ->specialization === null
        ) {
            throw ValidationException::withMessages([
                'new_supervisor_id' => [
                    'The replacement supervisor must have a specialization.',
                ],
            ]);
        }

        $leadSpecialization =
            $this->enumValue(
                $changedBy
                    ->supervisorProfile
                    ?->specialization
            );

        $currentSupervisorSpecialization =
            $this->enumValue(
                $assignment
                    ->supervisor
                    ?->supervisorProfile
                    ?->specialization
            );

        $newSupervisorSpecialization =
            $this->enumValue(
                $newSupervisor
                    ->supervisorProfile
                    ?->specialization
            );

        if (
            $leadSpecialization === null
            || $currentSupervisorSpecialization === null
            || $newSupervisorSpecialization === null
            || $leadSpecialization
                !== $currentSupervisorSpecialization
            || $leadSpecialization
                !== $newSupervisorSpecialization
        ) {
            throw ValidationException::withMessages([
                'new_supervisor_id' => [
                    'The replacement supervisor must belong to the same specialization as the supervisor lead and the current supervisor.',
                ],
            ]);
        }
    }

    private function snapshotEvaluation(
        ProjectEvaluation $evaluation
    ): array {
        return [
            'id' => $evaluation->id,

            'project_assignment_id' =>
                $evaluation->project_assignment_id,

            'student' => [
                'id' =>
                    $evaluation->student?->id,

                'name' =>
                    $evaluation->student?->name,

                'email' =>
                    $evaluation->student?->email,
            ],

            'supervisor' => [
                'id' =>
                    $evaluation->supervisor?->id,

                'name' =>
                    $evaluation->supervisor?->name,

                'email' =>
                    $evaluation->supervisor?->email,
            ],

            'status' =>
                $this->enumValue(
                    $evaluation->status
                ),

            'total_score' =>
                $evaluation->total_score,

            'final_grade' =>
                $evaluation->final_grade,

            'general_comment' =>
                $evaluation->general_comment,

            'summary_metrics' =>
                $evaluation->summary_metrics,

            'evaluated_at' =>
                $evaluation
                    ->evaluated_at
                    ?->toISOString(),

            'appeal_started_at' =>
                $evaluation
                    ->appeal_started_at
                    ?->toISOString(),

            'appeal_deadline_at' =>
                $evaluation
                    ->appeal_deadline_at
                    ?->toISOString(),

            'items' =>
                $evaluation
                    ->items
                    ->map(function ($item): array {
                        return [
                            'id' => $item->id,

                            'score' =>
                                $item->score,

                            'comment' =>
                                $item->comment,

                            'evidence' =>
                                $item->evidence,

                            'criteria' =>
                                $item->criteria
                                    ? [
                                        'id' =>
                                            $item
                                                ->criteria
                                                ->id,

                                        'name' =>
                                            $item
                                                ->criteria
                                                ->name,

                                        'max_score' =>
                                            $item
                                                ->criteria
                                                ->max_score,

                                        'weight' =>
                                            $item
                                                ->criteria
                                                ->weight,
                                    ]
                                    : null,

                            'evidences' =>
                                $item
                                    ->evidences
                                    ->map(
                                        fn ($evidence): array =>
                                            $evidence
                                                ->getAttributes()
                                    )
                                    ->values()
                                    ->all(),
                        ];
                    })
                    ->values()
                    ->all(),

            'appeals' =>
                $evaluation
                    ->appeals
                    ->map(fn ($appeal): array => [
                        'id' => $appeal->id,

                        'student_id' =>
                            $appeal->student_id,

                        'reason' =>
                            $appeal->reason,

                        'status' =>
                            $this->enumValue(
                                $appeal->status
                            ),

                        'evaluation_snapshot' =>
                            $appeal
                                ->evaluation_snapshot,

                        'reviewed_by' =>
                            $appeal->reviewed_by,

                        'review_notes' =>
                            $appeal->review_notes,

                        'reviewed_at' =>
                            $appeal
                                ->reviewed_at
                                ?->toISOString(),

                        'revision_request_id' =>
                            $appeal
                                ->revision_request_id,

                        'created_at' =>
                            $appeal
                                ->created_at
                                ?->toISOString(),
                    ])
                    ->values()
                    ->all(),

            'revision_requests' =>
                $evaluation
                    ->revisionRequests
                    ->map(
                        fn (
                            EvaluationRevisionRequest
                            $request
                        ): array => [
                            'id' => $request->id,

                            'requested_by' =>
                                $request->requested_by,

                            'assigned_to' =>
                                $request->assigned_to,

                            'source' =>
                                $this->enumValue(
                                    $request->source
                                ),

                            'source_reference_id' =>
                                $request
                                    ->source_reference_id,

                            'reason' =>
                                $request->reason,

                            'status' =>
                                $this->enumValue(
                                    $request->status
                                ),

                            'resolution_note' =>
                                $request
                                    ->resolution_note,

                            'resolved_at' =>
                                $request
                                    ->resolved_at
                                    ?->toISOString(),

                            'created_at' =>
                                $request
                                    ->created_at
                                    ?->toISOString(),
                        ]
                    )
                    ->values()
                    ->all(),
        ];
    }

    private function enumValue(
        mixed $value
    ): mixed {
        return $value instanceof BackedEnum
            ? $value->value
            : $value;
    }
}
