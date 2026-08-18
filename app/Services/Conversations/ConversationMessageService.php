<?php

namespace App\Services\Conversations;

use App\Events\ConversationMessageSent;
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

    public function getMessages(
        int $conversationId,
        int $userId,
        int $perPage = 30
    ): array {
        $conversation = $this->conversationRepository
            ->findUserConversationOrFail(
                conversationId: $conversationId,
                userId: $userId
            );

        $messages = $this->messageRepository
            ->getConversationMessages(
                conversationId: $conversationId,
                perPage: $perPage
            );

        return [
            'conversation' => $conversation,
            'messages' => $messages,
        ];
    }

    public function sendMessage(int $conversationId, int $senderId, array $data): Message
    {
        $conversation = $this->conversationRepository
            ->findUserConversationOrFail($conversationId, $senderId);

        if ($conversation->status === 'closed') {
            throw new AuthorizationException('This conversation is closed.');
        }

        $message = $this->messageRepository->createTextMessage(
            conversationId: $conversationId,
            senderId: $senderId,
            content: $data['content'],
        );

        ConversationMessageSent::dispatch($message);

        return $message;
    }

    public function updateMessage(
        int $messageId,
        int $userId,
        array $data
    ): Message {
        $message = $this->messageRepository
            ->findByIdOrFail($messageId);

        $conversation = $this->conversationRepository
            ->findUserConversationOrFail(
                conversationId: $message->conversation_id,
                userId: $userId,
            );

        if ($conversation->status === 'closed') {
            throw new AuthorizationException(
                'This conversation is closed.'
            );
        }

        if ($message->sender_id !== $userId) {
            throw new AuthorizationException(
                'You can only edit your own messages.'
            );
        }

        if ($message->type !== 'text') {
            throw new AuthorizationException(
                'Only text messages can be edited.'
            );
        }

        $wasRead = $this->messageRepository
            ->wasReadByAnotherParticipant(
                conversationId: $message->conversation_id,
                senderId: $userId,
                messageCreatedAt: $message->created_at,
            );

        if ($wasRead) {
            throw new AuthorizationException(
                'This message cannot be edited because it has already been read.'
            );
        }

        return $this->messageRepository->updateContent(
            message: $message,
            content: $data['content'],
        );
    }
}
