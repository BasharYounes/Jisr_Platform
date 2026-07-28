<?php

namespace App\Http\Resources\Supervisor;

use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EvaluationRevisionRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $source = $this->source;
        $status = $this->status;

        if ($source instanceof BackedEnum) {
            $source = $source->value;
        }

        if ($status instanceof BackedEnum) {
            $status = $status->value;
        }

        return [
            'id' => $this->id,

            'project_evaluation_id' =>
                $this->project_evaluation_id,

            'source' => $source,

            'reason' => $this->reason,

            'status' => $status,

            'requested_by' =>
                $this->whenLoaded(
                    'requestedBy',
                    fn (): array => [
                        'id' => $this->requestedBy->id,
                        'name' => $this->requestedBy->name,
                        'email' => $this->requestedBy->email,
                    ]
                ),

            'assigned_to' =>
                $this->whenLoaded(
                    'assignedTo',
                    fn (): array => [
                        'id' => $this->assignedTo->id,
                        'name' => $this->assignedTo->name,
                        'email' => $this->assignedTo->email,
                    ]
                ),

            'resolution_note' =>
                $this->resolution_note,

            'resolved_at' =>
                $this->resolved_at?->toISOString(),

            'created_at' =>
                $this->created_at?->toISOString(),
        ];
    }
}
