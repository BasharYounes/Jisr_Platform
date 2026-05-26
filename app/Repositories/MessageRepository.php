<?php

namespace App\Repositories;

use App\Interfaces\MessageRepositoryInterface;
use App\Models\Message;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MessageRepository implements MessageRepositoryInterface
{
    public function getConversationMessages(int $conversationId, int $perPage = 30): LengthAwarePaginator
    {
        return Message::query()
            ->where('conversation_id', $conversationId)
            ->with('sender:id,name,email')
            ->oldest()
            ->paginate($perPage);
    }

    public function createTextMessage(int $conversationId, int $senderId, string $content): Message
    {
        return Message::query()->create([
            'conversation_id' => $conversationId,
            'sender_id' => $senderId,
            'type' => 'text',
            'content' => $content,
        ]);
    }

    public function createSystemMessage(int $conversationId, string $content): Message
    {
        return Message::query()->create([
            'conversation_id' => $conversationId,
            'sender_id' => null,
            'type' => 'system',
            'content' => $content,
        ]);
    }
}