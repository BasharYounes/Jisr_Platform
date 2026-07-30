<?php

namespace App\Http\Resources\Supervisor;

use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ProjectAssignmentEvaluationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = $this->status;

        if ($status instanceof BackedEnum) {
            $status = $status->value;
        }

        return [
            'id' => $this->id,
            'project_assignment_id' => $this->project_assignment_id,
            'student' => [
                'id' => $this->student?->id,
                'name' => $this->student?->name,
                'email' => $this->student?->email,
            ],
            'supervisor' => [
                'id' => $this->supervisor?->id,
                'name' => $this->supervisor?->name,
                'email' => $this->supervisor?->email,
            ],
            'total_score' => $this->total_score,
            'final_grade' => $this->final_grade,
            'status' => $status,
            'general_comment' => $this->general_comment,
            'evaluated_at' => $this->evaluated_at?->toISOString(),
            'summary_metrics' => $this->summary_metrics,
        ];
    }
}
