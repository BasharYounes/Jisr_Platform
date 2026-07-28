<?php

namespace App\Domains\Supervisor\Actions;

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

        return User::query()
            ->role('supervisor')
            ->where('users.id', '!=', $supervisorLead->id)
            ->whereHas(
                'supervisorProfile',
                fn ($query) => $query->where(
                    'specialization',
                    $specialization->value
                )
            )
            ->with('supervisorProfile')
            ->orderBy('name')
            ->get();
    }
}
