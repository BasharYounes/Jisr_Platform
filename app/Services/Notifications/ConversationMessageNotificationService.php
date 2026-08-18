<?php

namespace App\Services\Notifications;

use App\Interfaces\MessageRepositoryInterface;
use App\Jobs\SendFirebaseNotificationJob;
use App\Models\CompanyTaskAssignment;
use App\Models\Message;
use App\Models\OpportunityInterview;

class ConversationMessageNotificationService
{
    public function __construct(
        private readonly MessageRepositoryInterface $messageRepository,
    ) {}

    public function send(Message $message): void
    {
        $conversation = $message->conversation;
        if (! $conversation) {
            return;
        }

        $conversation->loadMissing('participants.user', 'conversationable');

        $participantIds = $conversation->participants->pluck('user_id')->map(fn ($id) => (int) $id)->toArray();
        $participantIdsString = json_encode($participantIds);

        $context = $this->resolveConversationContext($conversation->conversationable);

        foreach ($conversation->participants as $participant) {
            $recipient = $participant->user;

            if (! $recipient || $recipient->id === $message->sender_id) {
                continue;
            }

            if (! $recipient->is_active) {
                continue;
            }

            $unreadCount = $this->messageRepository->countUnreadForParticipant(
                $conversation->id,
                $recipient->id
            );

            $body = $this->buildBody(
                $unreadCount,
                $context['label'],
                $context['title']
            );

            SendFirebaseNotificationJob::dispatch(
                recipient: $recipient,
                title: 'رسائل جديدة',
                body: $body,
                data: [
                    'type' => 'conversation_message',
                    'priority' => 'high',
                    'conversation_id' => (int) $conversation->id,
                    'conversation_type' => $context['type'],
                    'conversationable_id' => (int) $conversation->conversationable_id,
                    'sender_id' => (int) $message->sender_id,
                    'recipient_id' => (int) $recipient->id,
                    'participant_ids' => $participantIdsString,
                    'unread_count' => $unreadCount,
                ],
            );
        }
    }

    private function resolveConversationContext($conversationable): array
    {
        if ($conversationable instanceof CompanyTaskAssignment) {
            return [
                'type' => 'task',
                'label' => 'محادثة المهمة',
                'title' => $conversationable->task?->title,
            ];
        }

        if ($conversationable instanceof OpportunityInterview) {
            return [
                'type' => 'opportunity',
                'label' => 'محادثة فرصة العمل',
                'title' => $conversationable->opportunity?->title,
            ];
        }

        return [
            'type' => 'conversation',
            'label' => 'المحادثة',
            'title' => null,
        ];
    }

    private function buildBody(
        int $unreadCount,
        string $label,
        ?string $title
    ): string {
        $messagesText = $unreadCount === 1
            ? 'رسالة جديدة'
            : "{$unreadCount} رسائل جديدة";

        $conversationName = $title
            ? "{$label} \"{$title}\""
            : $label;

        return "لديك {$messagesText} في {$conversationName}.";
    }
}
