<?php

namespace App\Policies;

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

        /*
         * حماية إضافية من مراجعة المستخدم لتقييم أنشأه بنفسه.
         * ومع أننا اتفقنا أن المشرف الرئيسي لن يقيّم،
         * نبقي الحماية داخل النظام.
         */
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

    public function viewAsStudent(
        User $user,
        ProjectEvaluation $evaluation
    ): bool {
        return $user->hasRole('student')
            && (int) $evaluation->student_id
                === (int) $user->id
            && $evaluation->appeal_started_at !== null;
    }

    public function createAppeal(
        User $user,
        ProjectEvaluation $evaluation
    ): bool {
        if (! $this->viewAsStudent(
            $user,
            $evaluation
        )) {
            return false;
        }

        $allowedStatuses = [
            ProjectEvaluationStatus::SUBMITTED->value,
            ProjectEvaluationStatus::NEEDS_REVISION->value,
        ];

        return in_array(
            $evaluation->status,
            $allowedStatuses,
            true
        )
            && $evaluation->isAppealWindowOpen();
    }
}
