<?php

namespace App\Repositories;

use App\Interfaces\ConversationRepositoryInterface;
use App\Models\Conversation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

class ConversationRepository implements ConversationRepositoryInterface
{
    public function getUserConversations(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return Conversation::query()
            ->whereHas('participants', fn ($query) => $query->where('user_id', $userId))
            ->with([
                'participants.user:id,name,email',
                'latestMessage',
            ])
            ->latest()
            ->paginate($perPage);
    }

    public function findUserConversationOrFail(int $conversationId, int $userId): Conversation
    {
        return Conversation::query()
            ->whereKey($conversationId)
            ->whereHas('participants', fn ($query) => $query->where('user_id', $userId))
            ->with([
                'participants.user:id,name,email',
                'conversationable',
            ])
            ->firstOrFail();
    }

    public function findByConversationable(string $type, int $id): ?Conversation
    {
        return Conversation::query()
            ->where('conversationable_type', $type)
            ->where('conversationable_id', $id)
            ->first();
    }

    public function createForConversationable(Model $conversationable): Conversation
    {
        return Conversation::query()->create([
            'conversationable_type' => $conversationable::class,
            'conversationable_id' => $conversationable->id,
            'status' => 'open',
        ]);
    }
}