<?php

namespace App\Http\Resources\Supervisor;

use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

class LeadProjectEvaluationListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $pendingRevisionRequest =
            $this->relationLoaded('pendingRevisionRequest')
                ? $this->pendingRevisionRequest
                : null;

        return [
            'id' => $this->id,
            'project_assignment_id' => $this->project_assignment_id,
            'status' => $this->enumValue($this->status),
            'total_score' => $this->total_score,
            'final_grade' => $this->final_grade,
            'general_comment' => $this->general_comment,
            'evaluated_at' => $this->evaluated_at?->toISOString(),
            'appeal_deadline_at' => $this->appeal_deadline_at?->toISOString(),

            'has_pending_revision_request' => $pendingRevisionRequest !== null,

            'student' => [
                'id' => $this->student?->id,
                'name' => $this->student?->name,
                'email' => $this->student?->email,
            ],

            'supervisor' => [
                'id' => $this->supervisor?->id,
                'name' => $this->supervisor?->name,
                'email' => $this->supervisor?->email,
                'specialization' => $this->enumValue(
                    $this
                        ->supervisor
                        ?->supervisorProfile
                        ?->specialization
                ),
            ],

            'assignment' => [
                'id' => $this->assignment?->id,
                'status' => $this->enumValue(
                    $this->assignment?->status
                ),
                'project_template' => [
                    'id' => $this
                        ->assignment
                        ?->projectTemplate
                        ?->id,
                    'title' => $this
                        ->assignment
                        ?->projectTemplate
                        ?->title,
                    'level' => $this
                        ->assignment
                        ?->projectTemplate
                        ?->level,
                ],
            ],

            'actions' => [
                'can_request_revision' => Gate::allows(
                    'requestRevision',
                    $this->resource
                ),
            ],
        ];
    }

    private function enumValue(mixed $value): mixed
    {
        return $value instanceof BackedEnum
            ? $value->value
            : $value;
    }
}
