<?php

namespace App\Http\Resources\CompanyTasks;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyTaskReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'submission_id' => $this->company_task_submission_id,
            'assignment_id' => $this->company_task_assignment_id,

            'task' => [
                'id' => $this->assignment?->task?->id,
                'title' => $this->assignment?->task?->title,
                'deadline' => $this->assignment?->task?->deadline?->toISOString(),
            ],

            'student' => [
                'id' => $this->student?->id ?? $this->student_user_id,
                'name' => $this->student?->name,
                'email' => $this->student?->email,

                'profile_picture_url' => $this->student?->profile_picture_url
                    ? asset(
                        'storage/'.ltrim(
                            $this->student->profile_picture_url,
                            '/'
                        )
                    )
                    : null,
            ],

            'company' => [
                'id' => $this->company?->id,
                'name' => $this->company?->users?->first()?->name,
            ],

            'scores' => [
                'quality' => (int) $this->quality_score,
                'commitment' => (int) $this->commitment_score,
                'communication' => (int) $this->communication_score,
                'total' => (float) $this->total_score,
            ],

            'final_decision' => $this->final_decision,
            'feedback' => $this->feedback,

            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
