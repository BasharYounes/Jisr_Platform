<?php

namespace App\Http\Resources\Supervisor;

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
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'profile_picture_url' => $this->profile_picture_url,
            'specialization' =>
                $this->supervisorProfile?->specialization?->value,
            'is_volunteer' =>
                $this->supervisorProfile?->is_volunteer,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
