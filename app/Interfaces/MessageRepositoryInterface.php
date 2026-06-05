<?php

namespace App\Interfaces;

use App\Models\Message;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Carbon\CarbonInterface;

interface MessageRepositoryInterface
{
    public function getConversationMessages(int $conversationId, int $perPage = 30): LengthAwarePaginator;

    public function createTextMessage(int $conversationId, int $senderId, string $content): Message;

    public function createSystemMessage(int $conversationId, string $content): Message;

    public function findMessageInConversationOrFail(int $messageId,int $conversationId):Message;

    public function wasReadByAnotherParticipant(int $conversationId,int $senderId,$messageCreatedAt): bool;

    public function updateContent(Message $message,string $content):Message;

    public function findByIdOrFail(int $messageId): Message;

    public function markUnreadMessagesAsRead(
    int $conversationId,
    int $readerId,
    CarbonInterface $readAt
): int;
    
  }