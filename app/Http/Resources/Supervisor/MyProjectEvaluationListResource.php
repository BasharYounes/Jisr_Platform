<?php

namespace App\Http\Resources\Supervisor;

use App\Domains\Supervisor\Enums\ProjectEvaluationStatus;
use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

class MyProjectEvaluationListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $latestRevisionRequest =
            $this->relationLoaded('latestRevisionRequest')
                ? $this->latestRevisionRequest
                : null;

        $needsRevision =
            $this->enumValue($this->status)
                === ProjectEvaluationStatus::NEEDS_REVISION->value;

        return [
            'id' => $this->id,
            'project_assignment_id' => $this->project_assignment_id,
            'status' => $this->enumValue($this->status),
            'total_score' => $this->total_score,
            'final_grade' => $this->final_grade,
            'general_comment' => $this->general_comment,
            'evaluated_at' => $this->evaluated_at?->toISOString(),

            'student' => [
                'id' => $this->student?->id,
                'name' => $this->student?->name,
                'email' => $this->student?->email,
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

            'latest_revision_request' =>
                $latestRevisionRequest
                    ? [
                        'id' => $latestRevisionRequest->id,
                        'source' => $this->enumValue(
                            $latestRevisionRequest->source
                        ),
                        'reason' => $latestRevisionRequest->reason,
                        'status' => $this->enumValue(
                            $latestRevisionRequest->status
                        ),
                        'requested_by' => [
                            'id' => $latestRevisionRequest
                                ->requestedBy
                                ?->id,
                            'name' => $latestRevisionRequest
                                ->requestedBy
                                ?->name,
                            'email' => $latestRevisionRequest
                                ->requestedBy
                                ?->email,
                        ],
                        'created_at' => $latestRevisionRequest
                            ->created_at
                            ?->toISOString(),
                    ]
                    : null,

            'actions' => [
                'can_edit' =>
                    $needsRevision
                    && Gate::allows(
                        'update',
                        $this->resource
                    ),

                'can_resubmit' =>
                    $needsRevision
                    && Gate::allows(
                        'resubmit',
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
