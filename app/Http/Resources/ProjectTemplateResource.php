<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectTemplateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'expected_completion_date' => $this->expected_completion_date,
            'level' => $this->level,
            'max_students' => $this->max_students,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
