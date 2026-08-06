<?php

namespace App\Http\Resources\Chatbot;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class ChatbotConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $latestMessage = $this->relationLoaded('latestMessage')
            ? $this->latestMessage
            : null;

        return [
            'id' => $this->id,
            'mode' => $this->mode,
            'title' => $this->title,
            'last_message_preview' => $latestMessage !== null
                ? Str::limit($latestMessage->content, 80)
                : null,
            'last_message_at' => $this->last_message_at,
            'created_at' => $this->created_at,
        ];
    }
}
