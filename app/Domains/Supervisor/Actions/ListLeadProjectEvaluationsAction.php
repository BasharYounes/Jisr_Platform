<?php

namespace App\Domains\Supervisor\Actions;

use App\Models\ProjectEvaluation;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class ListLeadProjectEvaluationsAction
{
    public function execute(
        User $supervisorLead,
        array $filters
    ): LengthAwarePaginator {
        $supervisorLead->loadMissing('supervisorProfile');

        $specialization =
            $supervisorLead
                ->supervisorProfile
                ?->specialization;

        if ($specialization === null) {
            throw ValidationException::withMessages([
                'supervisor_profile' => [
                    'The supervisor lead must have a supervisor profile and specialization.',
                ],
            ]);
        }

        $perPage = (int) ($filters['per_page'] ?? 15);

        return ProjectEvaluation::query()
            ->whereHas(
                'supervisor.supervisorProfile',
                fn ($query) => $query->where(
                    'specialization',
                    $specialization->value
                )
            )
            ->when(
                $filters['status'] ?? null,
                fn ($query, $status) => $query->where(
                    'status',
                    $status
                )
            )
            ->with([
                'student:id,name,email',
                'supervisor:id,name,email',
                'supervisor.supervisorProfile',
                'assignment.projectTemplate',
                'pendingRevisionRequest',
            ])
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }
}
