<?php

namespace App\Http\Resources\Student;

use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentEvaluationAppealListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $appealStatus = $this->status;

        if ($appealStatus instanceof BackedEnum) {
            $appealStatus = $appealStatus->value;
        }

        $evaluationStatus =
            $this->evaluation?->status;

        if ($evaluationStatus instanceof BackedEnum) {
            $evaluationStatus =
                $evaluationStatus->value;
        }

        $assignmentStatus =
            $this->evaluation
                ?->assignment
                ?->status;

        if ($assignmentStatus instanceof BackedEnum) {
            $assignmentStatus =
                $assignmentStatus->value;
        }

        return [
            'id' => $this->id,

            'project_evaluation_id' => $this->project_evaluation_id,

            'reason' => $this->reason,

            'status' => $appealStatus,

            'is_pending' => $appealStatus === 'pending',

            'review_notes' => $this->review_notes,

            'reviewed_at' => $this->reviewed_at?->toISOString(),

            'revision_request_id' => $this->revision_request_id,

            'created_at' => $this->created_at?->toISOString(),

            'reviewed_by' => $this->whenLoaded(
                'reviewedBy',
                fn () => $this->reviewedBy
                    ? [
                        'id' => $this->reviewedBy->id,
                        'name' => $this->reviewedBy->name,
                        'email' => $this->reviewedBy->email,
                    ]
                    : null
            ),

            /*
             * The list intentionally omits evaluation_snapshot because it can
             * be large. The current evaluation/project context is enough for
             * the "My Appeals" screen. Snapshot remains available through the
             * existing evaluation details response.
             */
            'evaluation' => $this->whenLoaded(
                'evaluation',
                fn () => $this->evaluation
                    ? [
                        'id' => $this->evaluation->id,

                        'status' => $evaluationStatus,

                        'total_score' => $this->evaluation->total_score,

                        'final_grade' => $this->evaluation->final_grade,

                        'general_comment' => $this->evaluation->general_comment,

                        'evaluated_at' => $this->evaluation
                            ->evaluated_at
                            ?->toISOString(),

                        'appeal_started_at' => $this->evaluation
                            ->appeal_started_at
                            ?->toISOString(),

                        'appeal_deadline_at' => $this->evaluation
                            ->appeal_deadline_at
                            ?->toISOString(),

                        'assignment' => [
                            'id' => $this->evaluation
                                ->assignment?->id,

                            'status' => $assignmentStatus,

                            'progress_percentage' => $this->evaluation
                                ->assignment
                                ?->progress_percentage,

                            'project_template' => [
                                'id' => $this->evaluation
                                    ->assignment
                                    ?->projectTemplate
                                    ?->id,

                                'title' => $this->evaluation
                                    ->assignment
                                    ?->projectTemplate
                                    ?->title,

                                'level' => $this->evaluation
                                    ->assignment
                                    ?->projectTemplate
                                    ?->level,
                            ],
                        ],

                        'supervisor' => [
                            'id' => $this->evaluation
                                ->supervisor?->id,

                            'name' => $this->evaluation
                                ->supervisor?->name,

                            'email' => $this->evaluation
                                ->supervisor?->email,
                        ],
                    ]
                    : null
            ),
        ];
    }
}
