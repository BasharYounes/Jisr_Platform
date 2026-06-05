<?php

namespace App\Http\Resources\Conversation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\UserResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'sender_id' => $this->sender_id,

            'sender' => new UserResource(
                $this->whenLoaded('sender')
            ),

            'content' => $this->content,
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }
}