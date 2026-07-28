<?php

namespace App\Http\Resources\Conversation;

use App\Http\Resources\UserResource;
use App\Models\OpportunityInterview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpportunityConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $interview = (
            $this->relationLoaded('conversationable')
            && $this->conversationable instanceof OpportunityInterview
        )
            ? $this->conversationable
            : null;

        return [
            'id' => $this->id,
            'type' => $this->conversationable_type,
            'status' => $this->status,

            'opportunity_interview' => [
                'id' => $interview?->id
                    ?? $this->conversationable_id,

                'type' => $this->conversationable_type,
                'status' => $interview?->status,
                'scheduled_at' => $interview?->scheduled_at,
                'meeting_type' => $interview?->meeting_type,
                'meeting_link' => $interview?->meeting_link,
                'location' => $interview?->location,
            ],

            'opportunity' => $interview?->opportunity
                ? [
                    'id' => $interview->opportunity->id,
                    'title' => $interview->opportunity->title,
                    'type' => $interview->opportunity->type,
                    'status' => $interview->opportunity->status,
                ]
                : null,

            'unread_messages_count' => $this->unread_messages_count ?? 0,

            'latest_message' => $this->latestMessage
                ? [
                    'id' => $this->latestMessage->id,
                    'sender_id' => $this->latestMessage->sender_id,
                    'content' => $this->latestMessage->content,
                    'created_at' => $this->latestMessage->created_at,
                ]
                : null,

            'participants' => UserResource::collection(
                $this->whenLoaded('participants')
            ),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
