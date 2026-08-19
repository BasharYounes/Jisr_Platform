<?php

namespace App\Listeners;

use App\Events\ConversationMessageSent;
use App\Services\Notifications\ConversationMessageNotificationService;

class SendConversationMessagePushNotification
{
    public function __construct(
        private readonly ConversationMessageNotificationService $notificationService,
    ) {}

    public function handle(ConversationMessageSent $event): void
    {
        $this->notificationService->send($event->message);
    }
}
