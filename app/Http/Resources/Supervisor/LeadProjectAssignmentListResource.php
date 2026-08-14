<?php

namespace App\Http\Resources\Supervisor;

use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

class LeadProjectAssignmentListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $latestEvaluation =
            $this->relationLoaded('latestEvaluation')
                ? $this->latestEvaluation
                : null;

        return [
            'id' => $this->id,
            'status' => $this->enumValue($this->status),
            'progress_percentage' => $this->progress_percentage,
            'assigned_at' => $this->assigned_at?->toISOString(),

            'project_template' => [
                'id' => $this->projectTemplate?->id,
                'title' => $this->projectTemplate?->title,
                'level' => $this->projectTemplate?->level,
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
                'is_active' => (bool) $this
                    ->supervisor
                    ?->is_active,
            ],

            /*
             * An assignment may contain multiple students/evaluations.
             * This compact discovery endpoint exposes only the latest
             * evaluation as a quick status hint. Evaluation details remain
             * available through the dedicated evaluation endpoints.
             */
            'evaluation' => $latestEvaluation
                ? [
                    'id' => $latestEvaluation->id,
                    'status' => $this->enumValue(
                        $latestEvaluation->status
                    ),
                    'final_grade' => $latestEvaluation->final_grade,
                ]
                : null,

            'actions' => [
                'can_change_supervisor' => Gate::allows(
                    'changeSupervisor',
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
