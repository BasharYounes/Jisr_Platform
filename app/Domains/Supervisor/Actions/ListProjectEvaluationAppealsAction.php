<?php

namespace App\Domains\Supervisor\Actions;

use App\Domains\Supervisor\Enums\ProjectEvaluationAppealStatus;
use App\Models\ProjectEvaluationAppeal;
use App\Models\User;
use BackedEnum;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class ListProjectEvaluationAppealsAction
{
    public function execute(
        User $lead,
        ?ProjectEvaluationAppealStatus $status = null,
        int $perPage = 15
    ): LengthAwarePaginator {
        $lead->loadMissing('supervisorProfile');

        $specialization =
            $lead->supervisorProfile?->specialization;

        if ($specialization === null) {
            throw ValidationException::withMessages([
                'supervisor_profile' => [
                    'The supervisor lead must have a specialization.',
                ],
            ]);
        }

        $specializationValue =
            $specialization instanceof BackedEnum
                ? $specialization->value
                : (string) $specialization;

        return ProjectEvaluationAppeal::query()
            ->whereHas(
                'evaluation.supervisor.supervisorProfile',
                fn ($query) => $query->where(
                    'specialization',
                    $specializationValue
                )
            )
            ->when(
                $status !== null,
                fn ($query) => $query->where(
                    'status',
                    $status->value
                )
            )
            ->with([
                'student:id,name,email',
                'evaluation:id,project_assignment_id,student_id,supervisor_id,total_score,final_grade,status,appeal_deadline_at',
                'evaluation.supervisor:id,name,email',
                'reviewedBy:id,name,email',
            ])
            ->latest('id')
            ->paginate($perPage);
    }
}
