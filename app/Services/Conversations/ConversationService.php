<?php

namespace App\Services\Conversations;

use App\Interfaces\ConversationRepositoryInterface;
use App\Interfaces\MessageRepositoryInterface;
use App\Interfaces\ConversationParticipantRepositoryInterface;
use App\Models\Conversation;
use Illuminate\Support\Facades\DB;
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

    public function getUserOpenConversations(int $userId, int $perPage = 15)
{
    return $this->conversationRepository
        ->getUserOpenConversations($userId, $perPage);
}

public function getUserClosedConversations(int $userId, int $perPage = 15)
{
    return $this->conversationRepository
        
    ->getUserClosedConversations($userId, $perPage);
}

public function markAsRead(
    int $conversationId,
    int $userId
): void {
    $this->conversationRepository
        ->findUserConversationOrFail($conversationId, $userId);

    DB::transaction(function () use ($conversationId, $userId) {
        $readAt = now();

        $updated = $this->participantRepository->markAsRead(
            conversationId: $conversationId,
            userId: $userId,
            readAt: $readAt,
        );

        if (! $updated) {
            throw new \RuntimeException(
                'Conversation participant could not be updated.'
            );
        }

        $this->messageRepository->markUnreadMessagesAsRead(
            conversationId: $conversationId,
            readerId: $userId,
            readAt: $readAt,
        );
    });
}
}