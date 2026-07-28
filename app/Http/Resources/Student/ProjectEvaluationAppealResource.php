<?php

namespace App\Http\Resources\Student;

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

            'evaluation_snapshot' =>
                $this->evaluation_snapshot,

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
