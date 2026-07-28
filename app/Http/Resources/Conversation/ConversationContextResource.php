<?php

namespace App\Http\Resources\Conversation;

use App\Models\CompanyTaskAssignment;
use App\Models\OpportunityInterview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationContextResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $conversationable = $this->conversationable;

        return [
            'id' => $this->id,
            'type' => $this->conversationable_type,
            'status' => $this->status,

            'task' => $this->when(
                $conversationable instanceof CompanyTaskAssignment,
                function () use ($conversationable): array {
                    return [
                        'assignment_id' => $conversationable->id,
                        'id' => $conversationable->task?->id,
                        'title' => $conversationable->task?->title,
                        'deadline' => $conversationable->task?->deadline,
                        'assignment_status' => $conversationable->status,
                    ];
                }
            ),

            'opportunity' => $this->when(
                $conversationable instanceof OpportunityInterview,
                function () use ($conversationable): array {
                    return [
                        'interview_id' => $conversationable->id,
                        'id' => $conversationable->opportunity?->id,
                        'title' => $conversationable->opportunity?->title,
                        'type' => $conversationable->opportunity?->type,
                        'opportunity_status' => $conversationable->opportunity?->status,
                        'interview_status' => $conversationable->status,
                        'scheduled_at' => $conversationable->scheduled_at,
                    ];
                }
            ),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
