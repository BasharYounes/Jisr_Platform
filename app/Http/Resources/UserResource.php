<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'is_verified_by_admin' => $this->is_verified_by_admin ? 'Verified' : 'Not Verified',
            'profile_picture_url' => $this->profile_picture_url ? url('storage/profiles' . $this->profile_picture_url) : null, 
            'bio' => $this->bio,
          ];    
    }

}
