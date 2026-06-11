<?php

namespace App\Http\Resources\Conversation;

use App\Models\CompanyTaskAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $assignment = $this->conversationable instanceof CompanyTaskAssignment
            ? $this->conversationable
            : null;

        return [
            'id' => $this->id,
            'type' => $this->conversationable_type,
            'status' => $this->status,

            'task' => $this->when($assignment !== null, [
                'id' => $assignment?->task?->id,
                'title' => $assignment?->task?->title,
                'deadline' => $assignment?->task?->deadline,
            ]),

            'participants' => $this->whenLoaded('participants'),

            'latest_message' => $this->whenLoaded('latestMessage'),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
