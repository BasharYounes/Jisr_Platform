<?php

namespace App\Http\Resources;

use App\Http\Resources\CompanyStudents\CompanyStudentSkillResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
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

            'user' => new UserResource($this->whenLoaded('user')),

            'skills' => $this->when(
                $this->relationLoaded('user')
                && ($this->user?->relationLoaded('skills') ?? false),
                fn () => CompanyStudentSkillResource::collection(
                    $this->user->skills
                )
            ),

            'university' => $this->university,
            'major' => $this->major,
            'graduation_year' => $this->graduation_year,
            'phone' => $this->phone,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
