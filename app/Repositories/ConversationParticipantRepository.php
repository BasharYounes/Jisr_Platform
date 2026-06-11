<?php

namespace App\Repositories;

use App\Interfaces\ConversationParticipantRepositoryInterface;
use App\Models\ConversationParticipant;
use Carbon\CarbonInterface;

class ConversationParticipantRepository implements ConversationParticipantRepositoryInterface
{
    public function addParticipant(int $conversationId, int $userId, string $role): ConversationParticipant
    {
        return ConversationParticipant::query()->firstOrCreate(
            [
                'conversation_id' => $conversationId,
                'user_id' => $userId,
            ],
            [
                'role' => $role,
            ]
        );
    }

    public function exists(int $conversationId, int $userId): bool
    {
        return ConversationParticipant::query()
            ->where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->exists();
    }

    public function markAsRead(int $conversationId, int $userId, CarbonInterface $readAt): bool
    {
        return ConversationParticipant::query()
            ->where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->update([
                'last_read_at' => $readAt,
            ]) > 0;
    }
}
