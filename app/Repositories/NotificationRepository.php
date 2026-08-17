<?php

namespace App\Repositories;

use App\Interfaces\NotificationRepositoryInterface;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class NotificationRepository implements NotificationRepositoryInterface
{
    public function paginateForUser(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return Notification::query()
            ->with([
                'actor:id,name',
            ])
            ->where('user_id', $user->id)
            ->latest()
            ->paginate($perPage);
    }

    public function unreadCountForUser(User $user): int
    {
        return Notification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();
    }

    public function markAsRead(Notification $notification): Notification
    {
        $notification->markAsRead();

        return $notification->refresh();
    }

    public function markAllAsRead(User $user): int
    {
        return Notification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
