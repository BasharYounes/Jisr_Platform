<?php

namespace App\Repositories;

use App\Interfaces\MessageRepositoryInterface;
use App\Models\Message;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Models\ConversationParticipant;
use Carbon\CarbonInterface;

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

    public function findMessageInConversationOrFail(int $messageId,int $conversationId): Message {
    return Message::query()
        ->whereKey($messageId)
        ->where('conversation_id', $conversationId)
        ->firstOrFail();
    }

    public function wasReadByAnotherParticipant(int $conversationId,int $senderId,$messageCreatedAt): bool {
    return ConversationParticipant::query()
        ->where('conversation_id', $conversationId)
        ->where('user_id', '!=', $senderId)
        ->whereNotNull('last_read_at')
        ->where('last_read_at', '>=', $messageCreatedAt)
        ->exists();
    }

    public function updateContent(Message $message,string $content): Message {
    $message->update([
        'content' => $content,
    ]);

    $message->conversation?->touch();

    return $message->fresh(['sender']);
    }

    public function findByIdOrFail(int $messageId): Message
{
    return Message::query()->findOrFail($messageId);
}

public function markUnreadMessagesAsRead(
    int $conversationId,
    int $readerId,
    CarbonInterface $readAt
): int {
    return Message::query()
        ->where('conversation_id', $conversationId)
        ->where(function ($query) use ($readerId) {
            $query
                ->whereNull('sender_id')
                ->orWhere('sender_id', '!=', $readerId);
        })
        ->whereNull('read_at')
        ->update([
            'read_at' => $readAt,
        ]);
}

}