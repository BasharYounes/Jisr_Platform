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
}
