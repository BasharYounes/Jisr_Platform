<?php

namespace App\Domains\Admin\Actions;

use App\Domains\Admin\Enums\ManagedUserRole;
use App\Domains\Admin\Enums\UserRoleOperation;
use App\Domains\Supervisor\Enums\ProjectAssignmentStatus;
use App\Enums\SupervisorSpecialization;
use App\Models\AuditLog;
use App\Models\ProjectAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageUserRoleAction
{
    public function execute(
        User $admin,
        User $targetUser,
        UserRoleOperation $operation,
        ManagedUserRole $role,
        array $roleData = []
    ): User {
        if ($admin->is($targetUser)) {
            throw ValidationException::withMessages([
                'user' => [
                    'The admin cannot modify their own roles.',
                ],
            ]);
        }

        if (
            $operation === UserRoleOperation::Add
            && ! $targetUser->is_active
        ) {
            throw ValidationException::withMessages([
                'user' => [
                    'A supervisory role cannot be added to an inactive user.',
                ],
            ]);
        }

        return DB::transaction(function () use (
            $admin,
            $targetUser,
            $operation,
            $role,
            $roleData
        ): User {
            $targetUser->loadMissing('supervisorProfile');

            $oldRoles = $targetUser
                ->getRoleNames()
                ->sort()
                ->values()
                ->all();

            if ($operation === UserRoleOperation::Add) {
                $this->addRole(
                    $targetUser,
                    $role,
                    $roleData
                );
            } else {
                $this->removeRole(
                    $targetUser,
                    $role
                );
            }

            // منع Eloquent من إعادة الأدوار القديمة المحمّلة في الذاكرة.
            $targetUser->unsetRelation('roles');

            $newRoles = $targetUser
                ->getRoleNames()
                ->sort()
                ->values()
                ->all();

            AuditLog::create([
                'user_id' => $admin->id,
                'action' => $operation === UserRoleOperation::Add
                    ? 'user_role_added'
                    : 'user_role_removed',
                'entity_type' => User::class,
                'entity_id' => $targetUser->id,
                'old_value' => [
                    'roles' => $oldRoles,
                ],
                'new_value' => [
                    'roles' => $newRoles,
                    'operation' => $operation->value,
                    'changed_role' => $role->value,
                ],
            ]);

            return $targetUser
                ->refresh()
                ->load('supervisorProfile');
        });
    }

    private function addRole(
        User $targetUser,
        ManagedUserRole $role,
        array $roleData
    ): void {
        if ($targetUser->hasRole($role->value)) {
            throw ValidationException::withMessages([
                'role' => [
                    "The user already has the {$role->value} role.",
                ],
            ]);
        }

        match ($role) {
            ManagedUserRole::Supervisor =>
                $this->addSupervisorRole(
                    $targetUser,
                    $roleData
                ),

            ManagedUserRole::SupervisorLead =>
                $this->addSupervisorLeadRole(
                    $targetUser
                ),
        };
    }

    private function addSupervisorRole(
        User $targetUser,
        array $roleData
    ): void {
        $currentRoles = $targetUser->getRoleNames();

        /*
         * حاليًا الترقية إلى مشرف مسموحة فقط
         * لمستخدم دوره الحالي student فقط.
         */
        if (
            $currentRoles->count() !== 1
            || ! $currentRoles->contains('student')
        ) {
            throw ValidationException::withMessages([
                'role' => [
                    'The supervisor role can currently be added only to a student.',
                ],
            ]);
        }

        $this->prepareSupervisorProfile(
            $targetUser,
            $roleData
        );

        $targetUser->assignRole(
            ManagedUserRole::Supervisor->value
        );
    }

    private function prepareSupervisorProfile(
        User $targetUser,
        array $roleData
    ): void {
        $profile = $targetUser->supervisorProfile;

        $hasSpecialization =
            array_key_exists('specialization', $roleData)
            && $roleData['specialization'] !== null;

        $hasIsVolunteer =
            array_key_exists('is_volunteer', $roleData)
            && $roleData['is_volunteer'] !== null;

        /*
        * أول مرة يصبح فيها المستخدم مشرفًا:
        * الاختصاص وحالة التطوع إجباريان.
        */
        if ($profile === null) {
            $errors = [];

            if (! $hasSpecialization) {
                $errors['role_data.specialization'] = [
                    'The specialization field is required when creating the supervisor profile for the first time.',
                ];
            }

            if (! $hasIsVolunteer) {
                $errors['role_data.is_volunteer'] = [
                    'The is_volunteer field is required when creating the supervisor profile for the first time.',
                ];
            }

            if ($errors !== []) {
                throw ValidationException::withMessages(
                    $errors
                );
            }

            $profile = $targetUser
                ->supervisorProfile()
                ->create([
                    'specialization' =>
                        SupervisorSpecialization::from(
                            $roleData['specialization']
                        ),

                    'is_volunteer' =>
                        (bool) $roleData['is_volunteer'],
                ]);

            $targetUser->setRelation(
                'supervisorProfile',
                $profile
            );

            return;
        }

        /*
        * عند إعادة إضافة الدور:
        * نستخدم الملف القديم، مع تحديث القيم المرسلة فقط.
        */
        $updates = [];

        if ($hasSpecialization) {
            $updates['specialization'] =
                SupervisorSpecialization::from(
                    $roleData['specialization']
                );
        }

        if ($hasIsVolunteer) {
            $updates['is_volunteer'] =
                (bool) $roleData['is_volunteer'];
        }

        if ($updates !== []) {
            $profile->update($updates);
            $profile->refresh();
        }

        /*
        * حماية من وجود ملف قديم ناقص الاختصاص.
        */
        if ($profile->specialization === null) {
            throw ValidationException::withMessages([
                'role_data.specialization' => [
                    'The supervisor profile must have a specialization.',
                ],
            ]);
        }
    }

    private function addSupervisorLeadRole(
        User $targetUser
    ): void {
        if (! $targetUser->hasRole('supervisor')) {
            throw ValidationException::withMessages([
                'role' => [
                    'The user must be a supervisor before becoming a supervisor lead.',
                ],
            ]);
        }

        $profile = $targetUser->supervisorProfile;

        if (
            $profile === null
            || $profile->specialization === null
        ) {
            throw ValidationException::withMessages([
                'supervisor_profile' => [
                    'The supervisor must have a profile and specialization.',
                ],
            ]);
        }

        $specialization =
            $profile->specialization
            instanceof SupervisorSpecialization
                ? $profile->specialization->value
                : (string) $profile->specialization;

        /*
         * مشرف رئيسي واحد فقط لكل اختصاص.
         */
        $leadAlreadyExists = User::query()
            ->role('supervisor_lead')
            ->where('users.id', '!=', $targetUser->id)
            ->whereHas(
                'supervisorProfile',
                fn ($query) => $query->where(
                    'specialization',
                    $specialization
                )
            )
            ->exists();

        if ($leadAlreadyExists) {
            throw ValidationException::withMessages([
                'role' => [
                    "A supervisor lead already exists for the {$specialization} specialization.",
                ],
            ]);
        }

        $targetUser->assignRole(
            ManagedUserRole::SupervisorLead->value
        );
    }

    private function removeRole(
        User $targetUser,
        ManagedUserRole $role
    ): void {
        if (! $targetUser->hasRole($role->value)) {
            throw ValidationException::withMessages([
                'role' => [
                    "The user does not have the {$role->value} role.",
                ],
            ]);
        }

        match ($role) {
            ManagedUserRole::Supervisor =>
                $this->removeSupervisorRole(
                    $targetUser
                ),

            ManagedUserRole::SupervisorLead =>
                $this->removeSupervisorLeadRole(
                    $targetUser
                ),
        };
    }

    private function removeSupervisorRole(
        User $targetUser
    ): void {
        if ($targetUser->hasRole('supervisor_lead')) {
            throw ValidationException::withMessages([
                'role' => [
                    'Remove the supervisor_lead role before removing the supervisor role.',
                ],
            ]);
        }

        $activeStatuses = [
            ProjectAssignmentStatus::PENDING->value,
            ProjectAssignmentStatus::ASSIGNED->value,
            ProjectAssignmentStatus::IN_PROGRESS->value,
            ProjectAssignmentStatus::SUBMITTED->value,
            ProjectAssignmentStatus::UNDER_REVIEW->value,
        ];

        $hasActiveProjects = ProjectAssignment::query()
            ->where('supervisor_id', $targetUser->id)
            ->whereIn('status', $activeStatuses)
            ->exists();

        if ($hasActiveProjects) {
            throw ValidationException::withMessages([
                'role' => [
                    'The supervisor role cannot be removed while the user has active projects.',
                ],
            ]);
        }

        if ($targetUser->getRoleNames()->count() <= 1) {
            throw ValidationException::withMessages([
                'role' => [
                    'The last role of a user cannot be removed.',
                ],
            ]);
        }

        $targetUser->removeRole(
            ManagedUserRole::Supervisor->value
        );

        /*
         * لا نحذف SupervisorProfile.
         * نحتفظ به للتاريخ وإعادة الدور مستقبلًا.
         */
    }

    private function removeSupervisorLeadRole(
        User $targetUser
    ): void {
        $targetUser->removeRole(
            ManagedUserRole::SupervisorLead->value
        );

        /*
         * يبقى لديه supervisor وSupervisorProfile.
         */
    }
}
