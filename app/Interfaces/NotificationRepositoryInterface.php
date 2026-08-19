<?php

namespace App\Interfaces;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface NotificationRepositoryInterface
{
    public function paginateForUser(
        User $user,
        int $perPage = 20
    ): LengthAwarePaginator;

    public function unreadCountForUser(User $user): int;

    public function markAsRead(
        Notification $notification
    ): Notification;

    public function markAllAsRead(User $user): int;

    public function create(array $data): Notification;

    public function update(
        Notification $notification,
        array $data
    ): Notification;

    public function findUnreadConversationMessageNotification(
        int $userId,
        int $conversationId,
        string $type
    ): ?Notification;

    public function markConversationMessageNotificationAsRead(
        int $userId,
        int $conversationId,
        string $type
    ): int;
}
