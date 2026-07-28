<?php

namespace App\Http\Resources\CompanyTasks;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyTaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $companyUser = $this->company?->users?->first();

        $hasCloseStatus = $this->relationLoaded(
            'closeBlockingAssignments'
        );

        $blockingAssignments = $hasCloseStatus
            ? $this->getRelation('closeBlockingAssignments')
            : collect();

        $canClose = $this->status !== 'closed'
            && $blockingAssignments->isEmpty();

        return [
            'id' => $this->id,

            'company' => [
                'id' => $this->company?->id,
                'name' => $companyUser?->name,
                'industry' => $this->company?->industry,
            ],

            'title' => $this->title,
            'description' => $this->description,
            'difficulty_level' => $this->difficulty_level,
            'duration_days' => $this->duration_days,
            'deadline' => $this->deadline?->toISOString(),

            'max_applicants' => $this->max_applicants,
            'max_accepted_students' => $this->max_accepted_students,

            'accepted_students_count' =>
                $this->accepted_students_count ?? 0,

            'submissions_count' =>
                $this->submissions_count ?? 0,

            'deliverables' => $this->deliverables,
            'acceptance_criteria' => $this->acceptance_criteria,
            'submission_type' => $this->submission_type,
            'status' => $this->status,
            'published_at' => $this->published_at?->toISOString(),

            'canClose' => $this->when(
                $hasCloseStatus,
                $canClose
            ),

            'unreviewed_students' => $this->when(
                $hasCloseStatus,
                function () use ($blockingAssignments) {
                    return $blockingAssignments
                        ->map(function ($assignment): array {
                            return [
                                'assignment_id' => $assignment->id,

                                'student' => [
                                    'id' => $assignment->student?->id,
                                    'name' => $assignment->student?->name,
                                    'email' => $assignment->student?->email,

                                    'profile_picture_url' =>
                                        $assignment->student
                                            ?->profile_picture_url,
                                ],

                                'assignment_status' =>
                                    $assignment->status,

                                'has_review' =>
                                    $assignment->reviews->isNotEmpty(),
                            ];
                        })
                        ->values();
                }
            ),

            'skills' => $this->whenLoaded(
                'skills',
                function () {
                    return $this->skills->map(
                        function ($skill): array {
                            return [
                                'id' => $skill->id,
                                'name' => $skill->name,
                                'category' =>
                                    $skill->category ?? null,

                                'required_level' =>
                                    $skill->pivot->required_level,

                                'weight' =>
                                    (float) $skill->pivot->weight,

                                'mandatory' =>
                                    (bool) $skill->pivot->mandatory,
                            ];
                        }
                    );
                }
            ),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
