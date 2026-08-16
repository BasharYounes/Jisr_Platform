<?php

namespace App\Domains\Supervisor\Actions;

use App\Domains\Supervisor\Enums\ProjectAssignmentStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class ListSupervisorsBySpecializationAction
{
    public function execute(User $supervisorLead): Collection
    {
        $supervisorLead->loadMissing('supervisorProfile');

        $specialization =
            $supervisorLead->supervisorProfile?->specialization;

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

        return User::query()
            ->role('supervisor')
            ->where(
                'users.id',
                '!=',
                $supervisorLead->id
            )
            ->whereHas(
                'supervisorProfile',
                fn ($query) => $query->where(
                    'specialization',
                    $specialization->value
                )
            )
            ->with([
                'supervisorProfile',
                'roles',
            ])
            ->withCount([
                'supervisedAssignments as active_projects_count' =>
                    fn ($query) => $query->whereIn(
                        'status',
                        $activeStatuses
                    ),
            ])
            ->orderBy('name')
            ->get();
    }
}
