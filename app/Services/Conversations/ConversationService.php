<?php

namespace App\Services\Conversations;

use App\Interfaces\ConversationRepositoryInterface;
use App\Interfaces\MessageRepositoryInterface;
use App\Interfaces\ConversationParticipantRepositoryInterface;
use App\Models\Conversation;

class ConversationService
{
    public function __construct(
        private readonly ConversationRepositoryInterface $conversationRepository,
        private readonly MessageRepositoryInterface $messageRepository,
        private readonly ConversationParticipantRepositoryInterface $participantRepository,
    ) {}

    public function getUserConversations(int $userId, int $perPage = 15)
    {
        return $this->conversationRepository->getUserConversations($userId, $perPage);
    }

    public function getUserConversation(int $conversationId, int $userId): Conversation
    {
        return $this->conversationRepository->findUserConversationOrFail($conversationId, $userId);
    }

    public function getUserTaskConversations(int $userId, int $perPage = 15)
    {
        return $this->conversationRepository
            ->getUserTaskAssignmentConversations($userId, $perPage);
    }

    public function getMessages(int $conversationId, int $userId, int $perPage = 30)
    {
        $this->conversationRepository
            ->findUserConversationOrFail($conversationId, $userId);

        $messages = $this->messageRepository
            ->getConversationMessages($conversationId, $perPage);

        $this->participantRepository
            ->markAsRead($conversationId, $userId);

        return $messages;
    }
}