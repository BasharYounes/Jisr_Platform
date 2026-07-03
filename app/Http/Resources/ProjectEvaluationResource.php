<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProjectEvaluationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
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
            'status' => $this->status,
            'general_comment' => $this->general_comment,
            'summary_metrics' => $this->summary_metrics,
            'evaluated_at' => $this->evaluated_at,

            'assignment' => [
                'id' => $this->assignment?->id,
                'status' => $this->assignment?->status,
                'progress_percentage' => $this->assignment?->progress_percentage,

                'project_template' => [
                    'id' => $this->assignment?->projectTemplate?->id,
                    'title' => $this->assignment?->projectTemplate?->title,
                    'level' => $this->assignment?->projectTemplate?->level,
                ],
            ],

            'items' => $this->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'score' => $item->score,
                    'comment' => $item->comment,
                    'evidence' => $item->evidence,

                    'evidence_images' => $item->evidences
                        ->map(function ($evidence) {
                            return [
                                'id' => $evidence->id,
                                'url' => Storage::disk($evidence->disk)
                                    ->url($evidence->file_path),
                                'original_name' => $evidence->original_name,
                                'mime_type' => $evidence->mime_type,
                                'size_bytes' => $evidence->size_bytes,
                            ];
                        })
                        ->values(),

                    'criteria' => [
                        'id' => $item->criteria?->id,
                        'name' => $item->criteria?->name,
                        'description' => $item->criteria?->description,
                        'category' => $item->criteria?->category,
                        'max_score' => $item->criteria?->max_score,
                        'weight' => $item->criteria?->weight,
                        'scoring_anchors' => $item->criteria?->scoring_anchors,
                    ],
                ];
            }),
        ];
    }
}
