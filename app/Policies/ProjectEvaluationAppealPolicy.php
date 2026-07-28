<?php

namespace App\Policies;

use App\Models\ProjectEvaluationAppeal;
use App\Models\User;

class ProjectEvaluationAppealPolicy
{
    public function viewAny(User $user): bool
    {
        if (! $user->hasRole('supervisor_lead')) {
            return false;
        }

        $user->loadMissing('supervisorProfile');

        return $user->supervisorProfile?->specialization
            !== null;
    }

    public function view(
        User $user,
        ProjectEvaluationAppeal $appeal
    ): bool {
        return $this->isLeadForAppeal(
            $user,
            $appeal
        );
    }

    public function review(
        User $user,
        ProjectEvaluationAppeal $appeal
    ): bool {
        return $this->isLeadForAppeal(
            $user,
            $appeal
        );
    }

    private function isLeadForAppeal(
        User $user,
        ProjectEvaluationAppeal $appeal
    ): bool {
        if (! $user->hasRole('supervisor_lead')) {
            return false;
        }

        $user->loadMissing('supervisorProfile');

        $appeal->loadMissing(
            'evaluation.supervisor.supervisorProfile'
        );

        $leadSpecialization =
            $user->supervisorProfile?->specialization;

        $evaluationSupervisorSpecialization =
            $appeal
                ->evaluation
                ?->supervisor
                ?->supervisorProfile
                ?->specialization;

        return $leadSpecialization !== null
            && $evaluationSupervisorSpecialization !== null
            && $leadSpecialization
                === $evaluationSupervisorSpecialization;
    }
}
