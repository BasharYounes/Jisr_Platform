<?php

namespace App\Http\Resources\CompanyStudents;

use App\Http\Resources\PortfolioProjectResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyStudentDetailsResource extends JsonResource
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

            'bio' => $this->bio,

            'is_verified_by_admin' => (bool) $this->is_verified_by_admin,

            'student_profile' => $this->studentProfile
                ? [
                    'university' => $this->studentProfile->university,

                    'major' => $this->studentProfile->major,

                    'graduation_year' => $this->studentProfile->graduation_year,

                    'phone' => $this->studentProfile->phone,
                ]
                : null,

            'skills' => CompanyStudentSkillResource::collection(
                $this->whenLoaded('skills')
            ),

            'cvs' => CompanyStudentCvResource::collection(
                $this->whenLoaded('cvs')
            ),

            'portfolio_projects' => PortfolioProjectResource::collection(
                $this->whenLoaded('portfolioProjects')
            ),
        ];
    }
}
