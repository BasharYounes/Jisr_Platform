<?php

namespace App\Services\Notifications;

use App\Events\NotificationCreated;
use App\Interfaces\NotificationRepositoryInterface;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    public function __construct(
        private readonly NotificationRepositoryInterface $notificationRepository
    ) {}

    public function getUserNotifications(
        User $user,
        int $perPage = 20
    ): array {
        return [
            'notifications' => $this->notificationRepository
                ->paginateForUser($user, $perPage),

            'unread_count' => $this->notificationRepository
                ->unreadCountForUser($user),
        ];
    }

    public function getUnreadCount(User $user): int
    {
        return $this->notificationRepository->unreadCountForUser($user);
    }

    public function markAsRead(User $user, Notification $notification): Notification
    {
        abort_unless($notification->user_id === $user->id, 403);

        return $this->notificationRepository->markAsRead($notification);
    }

    public function markAllAsRead(User $user): int
    {
        return $this->notificationRepository->markAllAsRead($user);
    }

    public function send(
        User $recipient,
        string $type,
        string $title,
        ?string $body = null,
        ?User $actor = null,
        ?Model $related = null,
        array $data = []
    ): Notification {
        return DB::transaction(function () use (
            $recipient,
            $type,
            $title,
            $body,
            $actor,
            $related,
            $data
        ) {
            $notification = Notification::create([
                'user_id' => $recipient->id,
                'actor_id' => $actor?->id,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'notifiable_type' => $related?->getMorphClass(),
                'notifiable_id' => $related?->getKey(),
                'data' => $data,
            ]);

            broadcast(new NotificationCreated($notification));

            return $notification;
        });

        // ShouldQueueAfterCommit::dispatch(new NotificationCreated($notification));
    }
}
