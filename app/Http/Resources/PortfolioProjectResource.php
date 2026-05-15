<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PortfolioProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'user_id' => $this->user_id,

            'title' => $this->title,
            'description' => $this->description,
            'project_url' => $this->project_url,
            'source' => $this->source,

            'portfolioable' => $this->when($this->portfolioable_type && $this->portfolioable_id,fn () => [
            'type' => $this->portfolioable_type,
            'id' => $this->portfolioable_id,
            ]
            ),

            'completion_date' => $this->completion_date?->toISOString(),

            'grade' => $this->grade !== null
                ? (float) $this->grade
                : null,

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}