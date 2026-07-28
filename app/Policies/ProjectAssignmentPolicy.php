<?php

namespace App\Policies;

use App\Models\ProjectAssignment;
use App\Models\User;

class ProjectAssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('supervisor');
    }

    public function view(User $user, ProjectAssignment $projectAssignment): bool
    {
        return $user->hasRole('supervisor')
            && $projectAssignment->supervisor_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('supervisor');
    }

    public function changeSupervisor(
        User $user,
        ProjectAssignment $projectAssignment
    ): bool {
        if (! $user->hasRole('supervisor_lead')) {
            return false;
        }

        $user->loadMissing('supervisorProfile');

        $projectAssignment->loadMissing(
            'supervisor.supervisorProfile'
        );

        $leadSpecialization =
            $user->supervisorProfile?->specialization;

        $currentSupervisorSpecialization =
            $projectAssignment
                ->supervisor
                ?->supervisorProfile
                ?->specialization;

        return $leadSpecialization !== null
            && $currentSupervisorSpecialization !== null
            && $leadSpecialization
                === $currentSupervisorSpecialization;
    }
}
