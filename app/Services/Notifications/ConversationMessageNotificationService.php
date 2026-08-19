<?php

namespace App\Services\Notifications;

use App\Jobs\SendFirebaseNotificationJob;
use App\Models\Message;
use App\Support\NotificationTypes;

final class ConversationMessageNotificationService
{
    public function __construct(
        private readonly NotificationService $notificationService,
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
            $data = [
                'conversation_id' => $conversation->id,
                'message_id' => $message->id,
                'sender_user_id' => $sender->id,
                'screen' => 'conversation',
            ];

            // In-App + Realtime
            $this->notificationService->send(
                recipient: $recipient,
                type: NotificationTypes::CONVERSATION_MESSAGE_RECEIVED,
                title: 'رسالة جديدة',
                body: "{$sender->name}: {$message->content}",
                actor: $sender,
                related: $message,
                data: $data,
            );

            // Push
            SendFirebaseNotificationJob::dispatch(
                recipient: $recipient,
                title: 'رسالة جديدة',
                body: "{$sender->name}: {$message->content}",
                data: [
                    'type' => NotificationTypes::CONVERSATION_MESSAGE_RECEIVED,
                    ...$data,
                ],
            );
        }
    }
}
