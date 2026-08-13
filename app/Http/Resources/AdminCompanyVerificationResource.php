<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class AdminCompanyVerificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $owner = $this->relationLoaded('users')
            ? ($this->users->firstWhere('pivot.role', 'owner')
                ?? $this->users->first())
            : null;

        return [
            'id' => $this->id,
            'industry' => $this->industry,
            'location' => $this->location,
            'website' => $this->website,
            'documentation_file' => $this->documentation_file
                ? Storage::disk('public')->url($this->documentation_file)
                : null,
            'verification_status' => $owner?->is_verified_by_admin,
            'owner' => $owner
                ? [
                    'id' => $owner->id,
                    'name' => $owner->name,
                    'email' => $owner->email,
                ]
                : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
