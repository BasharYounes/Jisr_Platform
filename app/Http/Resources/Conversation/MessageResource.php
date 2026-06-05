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
    'type' => $this->type,
    'content' => $this->content,

    'sender' => $this->sender ? [
        'id' => $this->sender->id,
        'name' => $this->sender->name,
        'email' => $this->sender->email,
    ] : null,

    'is_mine' => $this->sender_id === $request->user()?->id,
    //'is_edited' => $this->updated_at->gt($this->created_at),

    'created_at' => $this->created_at,
    'updated_at' => $this->updated_at,
];
    }
}