<?php

namespace App\Services\Conversations;

use App\Interfaces\ConversationRepositoryInterface;
use App\Models\Conversation;

class ConversationService
{
    public function __construct(
        private readonly ConversationRepositoryInterface $conversationRepository,
    ) {}

    public function getUserConversations(int $userId, int $perPage = 15)
    {
        return $this->conversationRepository->getUserConversations($userId, $perPage);
    }

    public function getUserConversation(int $conversationId, int $userId): Conversation
    {
        return $this->conversationRepository->findUserConversationOrFail($conversationId, $userId);
    }
}