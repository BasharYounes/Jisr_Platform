<?php

namespace App\Events;

use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Queue\SerializesModels;
// use Illuminate\Foundation\Events\Dispatchable;
// use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;


class NotificationCreated implements ShouldBroadcast
{
    use SerializesModels;

    public function __construct(
        public Notification $notification
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('users.' . $this->notification->user_id);
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    public function broadcastWith(): array
    {
        return [
            'notification' => (new NotificationResource($this->notification))->resolve(),
            'unread_count' => Notification::query()
                ->where('user_id', $this->notification->user_id)
                ->whereNull('read_at')
                ->count(),
        ];
    }
}
