<?php

namespace App\Policies;

use App\Models\ProjectEvaluation;
use App\Models\User;

class ProjectEvaluationPolicy
{
    public function view(User $user, ProjectEvaluation $evaluation): bool
    {
        return $user->hasRole('supervisor') &&
                $evaluation->supervisor_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('supervisor');
    }
}
