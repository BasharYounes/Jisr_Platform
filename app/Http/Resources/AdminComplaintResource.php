<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminComplaintResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'complainant' => $this->whenLoaded(
                'complainant',
                fn (): array => [
                    'id' => $this->complainant?->id,
                    'name' => $this->complainant?->name,
                    'email' => $this->complainant?->email,
                ]
            ),
            'reported_user' => $this->whenLoaded(
                'reportedUser',
                fn (): array => [
                    'id' => $this->reportedUser?->id,
                    'name' => $this->reportedUser?->name,
                    'email' => $this->reportedUser?->email,
                ]
            ),
            'reason' => $this->reason,
            'status' => $this->status,
            'resolution_notes' => $this->resolution_notes,
            'resolved_at' => $this->resolved_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
