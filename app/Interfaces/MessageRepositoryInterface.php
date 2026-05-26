<?php

namespace App\Interfaces;

use App\Models\Message;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface MessageRepositoryInterface
{
    public function getConversationMessages(int $conversationId, int $perPage = 30): LengthAwarePaginator;

    public function createTextMessage(int $conversationId, int $senderId, string $content): Message;

    public function createSystemMessage(int $conversationId, string $content): Message;
}