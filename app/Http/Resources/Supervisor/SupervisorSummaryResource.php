<?php

namespace App\Http\Resources\Supervisor;

use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupervisorSummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $activeProjectsCount =
            (int) ($this->active_projects_count ?? 0);

        $isSupervisorLead =
            $this->relationLoaded('roles')
                ? $this->roles->contains(
                    'name',
                    'supervisor_lead'
                )
                : $this->hasRole('supervisor_lead');

        $isActive = (bool) $this->is_active;

        $canBeBlocked =
            $isActive
            && ! $isSupervisorLead
            && $activeProjectsCount === 0;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'profile_picture_url' => $this->profile_picture_url,
            'specialization' => $this->enumValue(
                $this->supervisorProfile?->specialization
            ),
            'is_volunteer' => $this->supervisorProfile?->is_volunteer,
            'is_active' => $isActive,

            'active_projects_count' => $activeProjectsCount,
            'can_be_blocked' => $canBeBlocked,
            'blocking_reason' => $this->blockingReason(
                isActive: $isActive,
                isSupervisorLead: $isSupervisorLead,
                activeProjectsCount: $activeProjectsCount,
            ),
        ];
    }

    private function blockingReason(
        bool $isActive,
        bool $isSupervisorLead,
        int $activeProjectsCount
    ): ?string {
        if (! $isActive) {
            return 'Supervisor account is already blocked.';
        }

        if ($isSupervisorLead) {
            return 'A supervisor lead cannot be blocked from this action.';
        }

        if ($activeProjectsCount > 0) {
            return 'The supervisor is responsible for active projects.';
        }

        return null;
    }

    private function enumValue(mixed $value): mixed
    {
        return $value instanceof BackedEnum
            ? $value->value
            : $value;
    }
}
