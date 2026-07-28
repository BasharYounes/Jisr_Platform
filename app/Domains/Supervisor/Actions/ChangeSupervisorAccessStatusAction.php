<?php

namespace App\Domains\Supervisor\Actions;

use App\Domains\Supervisor\Enums\ProjectAssignmentStatus;
use App\Models\AuditLog;
use App\Models\ProjectAssignment;
use App\Models\User;
use BackedEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChangeSupervisorAccessStatusAction
{
    public function block(
        User $lead,
        User $supervisor,
        string $reason
    ): array {
        return DB::transaction(function () use (
            $lead,
            $supervisor,
            $reason
        ): array {
            $lockedSupervisor =
                User::query()
                    ->whereKey($supervisor->id)
                    ->lockForUpdate()
                    ->firstOrFail();

            $this->validateLeadCanManageSupervisor(
                $lead,
                $lockedSupervisor
            );

            if (! (bool) $lockedSupervisor->is_active) {
                throw ValidationException::withMessages([
                    'supervisor' => [
                        'The supervisor is already blocked.',
                    ],
                ]);
            }

            if (
                $this->hasActiveProjects(
                    $lockedSupervisor
                )
            ) {
                throw ValidationException::withMessages([
                    'supervisor' => [
                        'The supervisor cannot be blocked while they are responsible for active projects.',
                    ],
                ]);
            }

            $oldSnapshot =
                $this->snapshotSupervisor(
                    $lockedSupervisor
                );

            $tokensRevoked =
                $lockedSupervisor
                    ->tokens()
                    ->count();

            /*
             * الحظر الحالي.
             */
            $lockedSupervisor->forceFill([
                'is_active' => false,
            ])->saveQuietly();

            /*
             * إنهاء جميع الجلسات الحالية.
             */
            $lockedSupervisor
                ->tokens()
                ->delete();

            $lockedSupervisor->refresh();

            $newSnapshot =
                $this->snapshotSupervisor(
                    $lockedSupervisor
                );

            AuditLog::create([
                'user_id' => $lead->id,

                'action' =>
                    'supervisor_blocked',

                'entity_type' =>
                    User::class,

                'entity_id' =>
                    $lockedSupervisor->id,

                'old_value' => [
                    'supervisor' =>
                        $oldSnapshot,
                ],

                'new_value' => [
                    'supervisor' =>
                        $newSnapshot,

                    'reason' =>
                        trim($reason),

                    'tokens_revoked' =>
                        $tokensRevoked,

                    'changed_at' =>
                        now()->toISOString(),
                ],
            ]);

            return [
                'supervisor' =>
                    $newSnapshot,

                'tokens_revoked' =>
                    $tokensRevoked,

                'requires_new_login' =>
                    true,

                'reason' =>
                    trim($reason),
            ];
        });
    }

    public function unblock(
        User $lead,
        User $supervisor,
        string $reason
    ): array {
        return DB::transaction(function () use (
            $lead,
            $supervisor,
            $reason
        ): array {
            $lockedSupervisor =
                User::query()
                    ->whereKey($supervisor->id)
                    ->lockForUpdate()
                    ->firstOrFail();

            $this->validateLeadCanManageSupervisor(
                $lead,
                $lockedSupervisor
            );

            if ((bool) $lockedSupervisor->is_active) {
                throw ValidationException::withMessages([
                    'supervisor' => [
                        'The supervisor account is already active.',
                    ],
                ]);
            }

            $oldSnapshot =
                $this->snapshotSupervisor(
                    $lockedSupervisor
                );

            $lockedSupervisor->forceFill([
                'is_active' => true,
            ])->saveQuietly();

            $lockedSupervisor->refresh();

            $newSnapshot =
                $this->snapshotSupervisor(
                    $lockedSupervisor
                );

            AuditLog::create([
                'user_id' => $lead->id,

                'action' =>
                    'supervisor_unblocked',

                'entity_type' =>
                    User::class,

                'entity_id' =>
                    $lockedSupervisor->id,

                'old_value' => [
                    'supervisor' =>
                        $oldSnapshot,
                ],

                'new_value' => [
                    'supervisor' =>
                        $newSnapshot,

                    'reason' =>
                        trim($reason),

                    'changed_at' =>
                        now()->toISOString(),
                ],
            ]);

            return [
                'supervisor' =>
                    $newSnapshot,

                /*
                 * Tokens المحذوفة أثناء الحظر لا تعود.
                 */
                'requires_new_login' =>
                    true,

                'reason' =>
                    trim($reason),
            ];
        });
    }

    private function validateLeadCanManageSupervisor(
        User $lead,
        User $supervisor
    ): void {
        $lead->loadMissing(
            'supervisorProfile'
        );

        $supervisor->loadMissing(
            'supervisorProfile'
        );

        if (! $lead->hasRole('supervisor_lead')) {
            throw ValidationException::withMessages([
                'lead' => [
                    'Only a supervisor lead can manage supervisor access.',
                ],
            ]);
        }

        if ($lead->is($supervisor)) {
            throw ValidationException::withMessages([
                'supervisor' => [
                    'The supervisor lead cannot block or unblock themselves.',
                ],
            ]);
        }

        if (! $supervisor->hasRole('supervisor')) {
            throw ValidationException::withMessages([
                'supervisor' => [
                    'The selected user is not a supervisor.',
                ],
            ]);
        }

        if (
            $supervisor->hasRole(
                'supervisor_lead'
            )
        ) {
            throw ValidationException::withMessages([
                'supervisor' => [
                    'A supervisor lead can manage only normal supervisors.',
                ],
            ]);
        }

        $leadSpecialization =
            $lead
                ->supervisorProfile
                ?->specialization;

        $supervisorSpecialization =
            $supervisor
                ->supervisorProfile
                ?->specialization;

        if (
            $leadSpecialization === null
            || $supervisorSpecialization === null
            || $leadSpecialization
                !== $supervisorSpecialization
        ) {
            throw ValidationException::withMessages([
                'supervisor' => [
                    'The supervisor must belong to the same specialization as the supervisor lead.',
                ],
            ]);
        }
    }

    private function hasActiveProjects(
        User $supervisor
    ): bool {
        $activeStatuses = [
            ProjectAssignmentStatus::PENDING->value,
            ProjectAssignmentStatus::ASSIGNED->value,
            ProjectAssignmentStatus::IN_PROGRESS->value,
            ProjectAssignmentStatus::SUBMITTED->value,
            ProjectAssignmentStatus::UNDER_REVIEW->value,
        ];

        return ProjectAssignment::query()
            ->where(
                'supervisor_id',
                $supervisor->id
            )
            ->whereIn(
                'status',
                $activeStatuses
            )
            ->exists();
    }

    private function snapshotSupervisor(
        User $supervisor
    ): array {
        $supervisor->loadMissing(
            'supervisorProfile'
        );

        return [
            'id' => $supervisor->id,
            'name' => $supervisor->name,
            'email' => $supervisor->email,

            'is_active' =>
                (bool) $supervisor->is_active,

            'roles' =>
                $supervisor
                    ->getRoleNames()
                    ->sort()
                    ->values()
                    ->all(),

            'specialization' =>
                $this->enumValue(
                    $supervisor
                        ->supervisorProfile
                        ?->specialization
                ),
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
