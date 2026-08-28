<?php

// use Illuminate\Support\Facades\Broadcast;

// Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
//     return (int) $user->id === (int) $id;
// });

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('users.{userId}', function (User $user, int $userId) {
    return (int) $user->id === $userId;
}, ['guards' => ['sanctum']]);

Broadcast::channel(
    'conversations.{conversationId}',
    function (User $user, int $conversationId) {
        return Conversation::query()
            ->whereKey($conversationId)
            ->whereHas(
                'participants',
                fn ($query) => $query->where('users.id', $user->id)
            )
            ->exists();
    },
    ['guards' => ['sanctum']]
);
