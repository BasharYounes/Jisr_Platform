<?php

namespace App\Services\Conversation;

use App\Interfaces\ConversationParticipantRepositoryInterface;
use App\Interfaces\ConversationRepositoryInterface;
use App\Interfaces\MessageRepositoryInterface;
use App\Models\Message;
use Illuminate\Auth\Access\AuthorizationException;

class ConversationMessageService
{
    public function __construct(
        private readonly ConversationRepositoryInterface $conversationRepository,
        private readonly MessageRepositoryInterface $messageRepository,
        private readonly ConversationParticipantRepositoryInterface $participantRepository,
    ) {}

    public function getMessages(int $conversationId, int $userId, int $perPage = 30)
    {
        $this->conversationRepository->findUserConversationOrFail($conversationId, $userId);

        return $this->messageRepository->getConversationMessages($conversationId, $perPage);
    }

    public function sendMessage(int $conversationId, int $senderId, array $data): Message
    {
        $conversation = $this->conversationRepository
            ->findUserConversationOrFail($conversationId, $senderId);

        if ($conversation->status === 'closed') {
            throw new AuthorizationException('This conversation is closed.');
        }

        return $this->messageRepository->createTextMessage(
            conversationId: $conversationId,
            senderId: $senderId,
            content: $data['content'],
        );
    }
}