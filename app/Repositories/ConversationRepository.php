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
                'participants:id,name,email,profile_picture_url',
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
            'participants:id,name,email,profile_picture_url',                
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
            'conversationable_type' => $conversationable->getMorphClass(),
            'conversationable_id' => $conversationable->id,
            'status' => 'open',
        ]);
    }

    public function getUserTaskAssignmentConversations(int $userId, int $perPage = 15)
{
    $type = (new \App\Models\CompanyTaskAssignment())->getMorphClass();

    return Conversation::query()
        ->where('conversationable_type', $type)
        ->whereHas('participants', fn ($query) =>
            $query->where('user_id', $userId)
        )
        ->with([
            'participants:id,name,email,profile_picture_url',
            'latestMessage',
            'conversationable',
        ])
        ->withCount([
            'messages as unread_messages_count' => function ($query) use ($userId) {
                $query
                    ->where(function ($q) use ($userId) {
                        $q->whereNull('sender_id')
                          ->orWhere('sender_id', '!=', $userId);
                    })
                    ->whereExists(function ($q) use ($userId) {
                        $q->selectRaw(1)
                            ->from('conversation_participants as cp')
                            ->whereColumn('cp.conversation_id', 'messages.conversation_id')
                            ->where('cp.user_id', $userId)
                            ->where(function ($qq) {
                                $qq->whereNull('cp.last_read_at')
                                   ->orWhereColumn('messages.created_at', '>', 'cp.last_read_at');
                            });
                    });
            },
        ])
        ->latest()
        ->paginate($perPage);
}
}