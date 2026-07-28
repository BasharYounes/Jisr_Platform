<?php

namespace App\Http\Resources\CompanyStudents;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyStudentCvResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->CvID,

            'file_url' => $this->FileUrl
                ? asset('storage/'.$this->FileUrl)
                : null,

            'is_primary' => (bool) $this->IsPrimary,

            'uploaded_at' => $this->UploadedAt?->toISOString(),
        ];
    }
}
