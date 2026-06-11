<?php

namespace App\Repositories;

use App\Interfaces\ConversationRepositoryInterface;
use App\Models\CompanyTaskAssignment;
use App\Models\Conversation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ConversationRepository implements ConversationRepositoryInterface
{
    public function getUserConversations(int $userId, int $perPage = 15)
    {
        return Conversation::query()
            ->whereHas('participants', fn ($query) => $query->where('user_id', $userId)
            )
            ->with([
                'participants:id,name,email,profile_picture_url',
                'latestMessage',

                'conversationable' => function (MorphTo $morphTo) {
                    $morphTo->morphWith([
                        CompanyTaskAssignment::class => [
                            'task.company.users:id,name',
                            'task.skills:id,name',
                            'student:id,name,email,profile_picture_url',
                        ],
                    ]);
                },
            ])
            ->latest()
            ->paginate($perPage);
    }

    public function findUserConversationOrFail(
        int $conversationId,
        int $userId
    ): Conversation {
        return Conversation::query()
            ->whereKey($conversationId)
            ->whereHas('participants', fn ($query) => $query->where('user_id', $userId)
            )
            ->with([
                'participants:id,name,email,profile_picture_url',
                'latestMessage',

                'conversationable' => function (MorphTo $morphTo) {
                    $morphTo->morphWith([
                        CompanyTaskAssignment::class => [
                            'task.company.users:id,name',
                            'task.skills:id,name',
                            'student:id,name,email,profile_picture_url',
                        ],
                    ]);
                },
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
        $type = (new CompanyTaskAssignment)->getMorphClass();

        return Conversation::query()
            ->where('status', 'open')
            ->where('conversationable_type', $type)
            ->whereHas('participants', fn ($query) => $query->where('user_id', $userId)
            )
            ->with([
                'participants:id,name,email,profile_picture_url',
                'latestMessage',

                'conversationable' => function (MorphTo $morphTo) {
                    $morphTo->morphWith([
                        CompanyTaskAssignment::class => [
                            'task.company.users:id,name',
                            'task.skills:id,name',
                            'student:id,name,email,profile_picture_url',
                        ],
                    ]);
                },
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

    public function getUserOpenConversations(int $userId, int $perPage = 15)
    {
        return Conversation::query()
            ->where('status', 'open')
            ->whereHas('participants', function ($query) use ($userId) {
                $query->where('users.id', $userId);
            })
            ->with([
                'participants:id,name,email,profile_picture_url',
                'latestMessage.sender:id,name,email,profile_picture_url',
                'conversationable',
            ])
            ->withCount([
                'messages as unread_messages_count' => function ($query) use ($userId) {
                    $query
                        ->where('sender_id', '!=', $userId)
                        ->whereExists(function ($subQuery) use ($userId) {
                            $subQuery
                                ->selectRaw('1')
                                ->from('conversation_participants as cp')
                                ->whereColumn(
                                    'cp.conversation_id',
                                    'messages.conversation_id'
                                )
                                ->where('cp.user_id', $userId)
                                ->where(function ($readQuery) {
                                    $readQuery
                                        ->whereNull('cp.last_read_at')
                                        ->orWhereColumn(
                                            'messages.created_at',
                                            '>',
                                            'cp.last_read_at'
                                        );
                                });
                        });
                },
            ])
            ->latest('updated_at')
            ->paginate($perPage);
    }

    public function getUserClosedConversations(int $userId, int $perPage = 15)
    {
        return Conversation::query()
            ->where('status', 'closed')
            ->whereHas('participants', function ($query) use ($userId) {
                $query->where('users.id', $userId);
            })
            ->with([
                'participants:id,name,email,profile_picture_url',
                'latestMessage.sender:id,name,email,profile_picture_url',
                'conversationable',
            ])
            ->withCount([
                'messages as unread_messages_count' => function ($query) use ($userId) {
                    $query
                        ->where('sender_id', '!=', $userId)
                        ->whereExists(function ($subQuery) use ($userId) {
                            $subQuery
                                ->selectRaw('1')
                                ->from('conversation_participants as cp')
                                ->whereColumn(
                                    'cp.conversation_id',
                                    'messages.conversation_id'
                                )
                                ->where('cp.user_id', $userId)
                                ->where(function ($readQuery) {
                                    $readQuery
                                        ->whereNull('cp.last_read_at')
                                        ->orWhereColumn(
                                            'messages.created_at',
                                            '>',
                                            'cp.last_read_at'
                                        );
                                });
                        });
                },
            ])
            ->latest('closed_at')
            ->paginate($perPage);
    }
}
