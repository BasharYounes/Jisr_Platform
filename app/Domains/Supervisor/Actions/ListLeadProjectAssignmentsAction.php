<?php

namespace App\Domains\Supervisor\Actions;

use App\Domains\Supervisor\Enums\ProjectAssignmentStatus;
use App\Models\ProjectAssignment;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class ListLeadProjectAssignmentsAction
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

        $activeStatuses = [
            ProjectAssignmentStatus::PENDING->value,
            ProjectAssignmentStatus::ASSIGNED->value,
            ProjectAssignmentStatus::IN_PROGRESS->value,
            ProjectAssignmentStatus::SUBMITTED->value,
            ProjectAssignmentStatus::UNDER_REVIEW->value,
        ];

        $perPage = (int) ($filters['per_page'] ?? 15);
        $search = trim((string) ($filters['search'] ?? ''));

        return ProjectAssignment::query()
            ->whereIn(
                'status',
                $activeStatuses
            )
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
            ->when(
                $filters['supervisor_id'] ?? null,
                fn ($query, $supervisorId) => $query->where(
                    'supervisor_id',
                    $supervisorId
                )
            )
            ->when(
                $search !== '',
                function ($query) use ($search): void {
                    $query->where(function ($query) use ($search): void {
                        $query
                            ->whereHas(
                                'projectTemplate',
                                fn ($templateQuery) => $templateQuery
                                    ->where(
                                        'title',
                                        'like',
                                        '%'.$search.'%'
                                    )
                            )
                            ->orWhereHas(
                                'supervisor',
                                fn ($supervisorQuery) => $supervisorQuery
                                    ->where(
                                        'name',
                                        'like',
                                        '%'.$search.'%'
                                    )
                                    ->orWhere(
                                        'email',
                                        'like',
                                        '%'.$search.'%'
                                    )
                            );
                    });
                }
            )
            ->with([
                'projectTemplate',
                'supervisor:id,name,email,is_active',
                'supervisor.supervisorProfile',
                'latestEvaluation' => fn ($query) => $query->select([
                    'project_evaluations.id',
                    'project_evaluations.project_assignment_id',
                    'project_evaluations.status',
                    'project_evaluations.final_grade',
                ]),
            ])
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }
}
