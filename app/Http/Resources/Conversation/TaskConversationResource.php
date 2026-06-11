<?php

namespace App\Http\Resources\Conversation;

use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,

            'task_assignment' => [
                'id' => $this->conversationable_id,
                'type' => $this->conversationable_type,
            ],

            'unread_messages_count' => $this->unread_messages_count ?? 0,

            'latest_message' => $this->latestMessage ? [
                'id' => $this->latestMessage->id,
                'sender_id' => $this->latestMessage->sender_id,
                'content' => $this->latestMessage->content,
                'created_at' => $this->latestMessage->created_at,
            ] : null,

            'participants' => UserResource::collection(
                $this->whenLoaded('participants')
            ),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
