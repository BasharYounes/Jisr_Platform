<?php

namespace App\Http\Resources\CompanyTasks;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyTaskApplicationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $companyUser = $this->task?->company?->users?->first();
        return [
          'id' => $this->id,

            'task' => [
                'id' => $this->task?->id,
                'title' => $this->task?->title,
                'difficulty_level' => $this->task?->difficulty_level,
                'deadline' => $this->task?->deadline?->toISOString(),

                'company' => [
                    'id' => $this->task?->company?->id,
                    'name' => $companyUser?->name,
                    'industry' => $this->task?->company?->industry,              
                    ],
            ],

            'student_user_id' => $this->student_user_id,

            'message' => $this->message,
            'portfolio_url' => $this->portfolio_url,
            'github_url' => $this->github_url,

            'status' => $this->status,

            'match_score' => $this->when(
                isset($this->match_score),
                fn () => (float) $this->match_score
            ),

            'match_reasons' => $this->when(
                isset($this->match_reasons),
                fn () => $this->match_reasons
            ),


            'applied_at' => $this->applied_at?->toISOString(),
            'reviewed_at' => $this->reviewed_at?->toISOString(),

            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
