<?php

namespace App\Interfaces;

use App\Models\Conversation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

interface ConversationRepositoryInterface
{
    public function getUserConversations(int $userId, int $perPage = 15): LengthAwarePaginator;

    public function findUserConversationOrFail(int $conversationId, int $userId): Conversation;

    public function findByConversationable(string $type, int $id): ?Conversation;

    public function createForConversationable(Model $conversationable): Conversation;
    public function getUserTaskAssignmentConversations(int $userId, int $perPage = 15);

    public function getUserOpenConversations(int $userId, int $perPage = 15);

    public function getUserClosedConversations(int $userId, int $perPage = 15);
}