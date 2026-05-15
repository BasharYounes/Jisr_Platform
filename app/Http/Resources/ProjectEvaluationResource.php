<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectEvaluationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'project_assignment_id' => $this->project_assignment_id,

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

                'members' =>[
                    'student' => [
                        'id' => $this->assignment?->student?->id,
                        'name' => $this->assignment?->student?->name,
                        'email' => $this->assignment?->student?->email,
                    ],
                ],

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
                    'evidence_urls' => $item->evidence_urls,

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
