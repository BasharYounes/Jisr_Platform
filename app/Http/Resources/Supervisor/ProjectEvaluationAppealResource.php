<?php

namespace App\Http\Resources\Supervisor;

use App\Http\Resources\ProjectEvaluationResource;
use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectEvaluationAppealResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = $this->status;

        if ($status instanceof BackedEnum) {
            $status = $status->value;
        }

        return [
            'id' => $this->id,

            'project_evaluation_id' =>
                $this->project_evaluation_id,

            'reason' => $this->reason,

            'status' => $status,

            /*
             * النسخة التي اعترض الطالب عليها.
             */
            'evaluation_snapshot' =>
                $this->evaluation_snapshot,

            /*
             * النسخة الحالية بعد أي تعديلات لاحقة.
             */
            'current_evaluation' =>
                $this->whenLoaded(
                    'evaluation',
                    fn () =>
                        (new ProjectEvaluationResource(
                            $this->evaluation
                        ))->resolve($request)
                ),

            'student' =>
                $this->whenLoaded(
                    'student',
                    fn (): array => [
                        'id' => $this->student->id,
                        'name' => $this->student->name,
                        'email' => $this->student->email,
                    ]
                ),

            'reviewed_by' =>
                $this->reviewedBy
                    ? [
                        'id' => $this->reviewedBy->id,
                        'name' => $this->reviewedBy->name,
                        'email' => $this->reviewedBy->email,
                    ]
                    : null,

            'review_notes' =>
                $this->review_notes,

            'reviewed_at' =>
                $this->reviewed_at?->toISOString(),

            'revision_request_id' =>
                $this->revision_request_id,

            'created_at' =>
                $this->created_at?->toISOString(),
        ];
    }
}
