<?php

namespace App\Services\Notifications;

use App\Events\NotificationCreated;
use App\Interfaces\MessageRepositoryInterface;
use App\Interfaces\NotificationRepositoryInterface;
use App\Jobs\SendFirebaseNotificationJob;
use App\Models\Message;
use App\Support\NotificationTypes;
use Illuminate\Support\Facades\DB;

final class ConversationMessageNotificationService
{
    public function __construct(
        private readonly NotificationRepositoryInterface $notificationRepository,
        private readonly MessageRepositoryInterface $messageRepository,
    ) {}

    public function send(Message $message): void
    {
        $message->loadMissing([
            'sender:id,name',
            'conversation.participants:id,name',
        ]);

        $sender = $message->sender;
        $conversation = $message->conversation;

        if ($sender === null || $conversation === null) {
            return;
        }

        $recipients = $conversation->participants
            ->where('id', '!=', $sender->id);

        foreach ($recipients as $recipient) {
            $unreadCount = $this->messageRepository->countUnreadForParticipant(
                $conversation->id,
                $recipient->id
            );

            $title = $unreadCount === 1
                ? 'رسالة جديدة'
                : "{$unreadCount} رسائل جديدة";

            $body = "{$sender->name}: {$message->content}";

            $data = [
                'conversation_id' => $conversation->id,
                'conversationable_type' => $conversation->conversationable_type,
                'message_id' => $message->id,
                'sender_user_id' => $sender->id,
                'unread_count' => $unreadCount,
                'screen' => 'conversation',
            ];

            $notification = DB::transaction(function () use (
                $recipient,
                $sender,
                $message,
                $title,
                $body,
                $data
            ) {
                $existingNotification = $this->notificationRepository
                    ->findUnreadConversationMessageNotification(
                        userId: $recipient->id,
                        conversationId: $message->conversation_id,
                        type: NotificationTypes::CONVERSATION_MESSAGE_RECEIVED
                    );

                $attributes = [
                    'user_id' => $recipient->id,
                    'actor_id' => $sender->id,
                    'type' => NotificationTypes::CONVERSATION_MESSAGE_RECEIVED,
                    'title' => $title,
                    'body' => $body,
                    'notifiable_type' => $message->getMorphClass(),
                    'notifiable_id' => $message->getKey(),
                    'data' => $data,
                ];

                if ($existingNotification !== null) {
                    return $this->notificationRepository->update(
                        notification: $existingNotification,
                        data: $attributes,
                    );
                }

                return $this->notificationRepository->create(
                    $attributes
                );
            });

            $notification->loadMissing('actor:id,name');

            broadcast(
                new NotificationCreated($notification)
            );

            SendFirebaseNotificationJob::dispatch(
                recipient: $recipient,
                title: $title,
                body: $body,
                data: [
                    'type' => NotificationTypes::CONVERSATION_MESSAGE_RECEIVED,
                    ...$data,
                ],
            );
        }
    }

    public function markAsRead(
        int $conversationId,
        int $userId
    ): int {
        return $this->notificationRepository
            ->markConversationMessageNotificationAsRead(
                userId: $userId,
                conversationId: $conversationId,
                type: NotificationTypes::CONVERSATION_MESSAGE_RECEIVED,
            );
    }
}
