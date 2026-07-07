<?php

namespace App\Http\Resources\Points;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PointTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'points' => $this->points,
            'action_type' => $this->actionType?->action_type,
            'category' => $this->actionType?->category?->name,
            'description' => $this->description,
            'reference' => [
                'type' => class_basename($this->reference_type),
                'id' => $this->reference_id,
            ],
            'created_at' => $this->created_at,
        ];
    }
}
