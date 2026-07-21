<?php

namespace App\Http\Resources\Supervisor;

use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectEvaluationAppealSummaryResource extends JsonResource
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

            'student' => [
                'id' => $this->student?->id,
                'name' => $this->student?->name,
                'email' => $this->student?->email,
            ],

            'evaluation' => [
                'id' => $this->evaluation?->id,

                'total_score' =>
                    $this->evaluation?->total_score,

                'final_grade' =>
                    $this->evaluation?->final_grade,

                'status' =>
                    $this->evaluation?->status,

                'appeal_deadline_at' =>
                    $this->evaluation
                        ?->appeal_deadline_at
                        ?->toISOString(),

                'supervisor' => [
                    'id' =>
                        $this->evaluation
                            ?->supervisor
                            ?->id,

                    'name' =>
                        $this->evaluation
                            ?->supervisor
                            ?->name,

                    'email' =>
                        $this->evaluation
                            ?->supervisor
                            ?->email,
                ],
            ],

            'reviewed_by' =>
                $this->reviewedBy
                    ? [
                        'id' => $this->reviewedBy->id,
                        'name' => $this->reviewedBy->name,
                        'email' => $this->reviewedBy->email,
                    ]
                    : null,

            'created_at' =>
                $this->created_at?->toISOString(),
        ];
    }
}
