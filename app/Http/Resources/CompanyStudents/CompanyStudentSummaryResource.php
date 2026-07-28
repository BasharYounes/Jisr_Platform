<?php

namespace App\Http\Resources\CompanyStudents;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyStudentSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'name' => $this->name,

            'email' => $this->email,

            'profile_picture_url' => $this->profile_picture_url
                ? asset('storage/'.$this->profile_picture_url)
                : null,
        ];
    }
}
