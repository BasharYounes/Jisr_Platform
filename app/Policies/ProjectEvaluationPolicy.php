<?php

namespace App\Policies;

use App\Domains\Supervisor\Enums\ProjectEvaluationAppealStatus;
use App\Domains\Supervisor\Enums\ProjectEvaluationStatus;
use App\Models\ProjectEvaluation;
use App\Models\User;

class ProjectEvaluationPolicy
{
    public function view(
        User $user,
        ProjectEvaluation $evaluation
    ): bool {
        return $user->hasRole('supervisor')
            && (int) $evaluation->supervisor_id
                === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('supervisor');
    }

    public function approve(
        User $user,
        ProjectEvaluation $evaluation
    ): bool {
        return $this->isLeadForEvaluationSupervisor(
            $user,
            $evaluation
        );
    }

    public function requestRevision(
        User $user,
        ProjectEvaluation $evaluation
    ): bool {
        return $this->isLeadForEvaluationSupervisor(
            $user,
            $evaluation
        );
    }

    private function isLeadForEvaluationSupervisor(
        User $user,
        ProjectEvaluation $evaluation
    ): bool {
        if (! $user->hasRole('supervisor_lead')) {
            return false;
        }

        if (
            (int) $evaluation->supervisor_id
            === (int) $user->id
        ) {
            return false;
        }

        $user->loadMissing('supervisorProfile');

        $evaluation->loadMissing(
            'supervisor.supervisorProfile'
        );

        $leadSpecialization =
            $user->supervisorProfile?->specialization;

        $evaluationSupervisorSpecialization =
            $evaluation
                ->supervisor
                ?->supervisorProfile
                ?->specialization;

        return $leadSpecialization !== null
            && $evaluationSupervisorSpecialization !== null
            && $leadSpecialization
                === $evaluationSupervisorSpecialization;
    }

    public function update(
        User $user,
        ProjectEvaluation $evaluation
    ): bool {
        return $this->view(
            $user,
            $evaluation
        );
    }

    public function resubmit(
        User $user,
        ProjectEvaluation $evaluation
    ): bool {
        return $this->view(
            $user,
            $evaluation
        );
    }

    /**
     * Authorization-only rule for attempting to submit an appeal.
     *
     * This deliberately checks identity/ownership only. Business rules such
     * as pending appeal, evaluation status, and appeal deadline belong to the
     * Action so legitimate students receive a descriptive 422 response rather
     * than a generic 403 authorization response.
     */
    public function submitAppeal(
        User $user,
        ProjectEvaluation $evaluation
    ): bool {
        return $user->hasRole('student')
            && (int) $evaluation->student_id
                === (int) $user->id;
    }

    public function viewAsStudent(
        User $user,
        ProjectEvaluation $evaluation
    ): bool {
        return $this->submitAppeal(
            $user,
            $evaluation
        )
            && $evaluation->appeal_started_at !== null;
    }

    /**
     * UI capability rule.
     *
     * This remains the source of truth for `can_appeal`.
     * It answers whether an appeal can be created right now.
     */
    public function createAppeal(
        User $user,
        ProjectEvaluation $evaluation
    ): bool {
        if (! $this->submitAppeal(
            $user,
            $evaluation
        )) {
            return false;
        }

        if (
            $evaluation->status
            !== ProjectEvaluationStatus::SUBMITTED->value
        ) {
            return false;
        }

        if (! $evaluation->isAppealWindowOpen()) {
            return false;
        }

        return ! $evaluation
            ->appeals()
            ->where('student_id', $user->id)
            ->where(
                'status',
                ProjectEvaluationAppealStatus::Pending->value
            )
            ->exists();
    }
}
