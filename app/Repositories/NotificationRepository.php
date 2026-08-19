<?php

namespace App\Repositories;

use App\Interfaces\NotificationRepositoryInterface;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class NotificationRepository implements NotificationRepositoryInterface
{
    public function paginateForUser(
        User $user,
        int $perPage = 20
    ): LengthAwarePaginator {
        return Notification::query()
            ->with([
                'actor:id,name',
            ])
            ->where('user_id', $user->id)
            ->latest('updated_at')
            ->paginate($perPage);
    }

    public function unreadCountForUser(User $user): int
    {
        return Notification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();
    }

    public function markAsRead(
        Notification $notification
    ): Notification {
        $notification->markAsRead();

        return $notification->refresh();
    }

    public function markAllAsRead(User $user): int
    {
        return Notification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
            ]);
    }

    public function create(array $data): Notification
    {
        return Notification::query()->create($data);
    }

    public function update(
        Notification $notification,
        array $data
    ): Notification {
        $notification->fill($data);
        $notification->save();

        return $notification->refresh();
    }

    public function findUnreadConversationMessageNotification(
        int $userId,
        int $conversationId,
        string $type
    ): ?Notification {
        return Notification::query()
            ->where('user_id', $userId)
            ->where('type', $type)
            ->whereNull('read_at')
            ->where('data->conversation_id', $conversationId)
            ->latest('updated_at')
            ->first();
    }

    public function markConversationMessageNotificationAsRead(
        int $userId,
        int $conversationId,
        string $type
    ): int {
        return Notification::query()
            ->where('user_id', $userId)
            ->where('type', $type)
            ->whereNull('read_at')
            ->where('data->conversation_id', $conversationId)
            ->update([
                'read_at' => now(),
            ]);
    }
}
