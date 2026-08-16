<?php

namespace App\Http\Resources\Chatbot;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatbotMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role,
            'content' => $this->content,
            'language' => $this->language,
            'status' => $this->status,
            'actions' => $this->actions ?? [],
            'created_at' => $this->created_at,
        ];
    }
}
