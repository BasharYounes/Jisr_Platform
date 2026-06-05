<?php

namespace App\Http\Resources\Conversation;

use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'type' => $this->conversationable_type,

            'context_id' => $this->conversationable_id,

            'status' => $this->status,

            'unread_messages_count' =>
                $this->unread_messages_count ?? 0,

            'latest_message' => $this->whenLoaded(
                'latestMessage',
                function () {
                    if (!$this->latestMessage) {
                        return null;
                    }

                    return [
                        'id' => $this->latestMessage->id,
                        'content' => $this->latestMessage->content,
                        'sender_id' => $this->latestMessage->sender_id,
                        'created_at' => $this->latestMessage->created_at,
                    ];
                }
            ),

            'participants' => UserResource::collection(
                $this->whenLoaded('participants')
            ),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}