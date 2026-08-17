<?php

namespace App\Listeners;

use App\Events\CompanyTaskApplicationAccepted;
use App\Jobs\SendFirebaseNotificationJob;
use App\Services\Notifications\NotificationService;
use App\Support\NotificationTypes;

final class SendCompanyTaskApplicationAcceptedNotification
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function handle(
        CompanyTaskApplicationAccepted $event
    ): void {
        $application = $event->application;
        $assignment = $event->assignment;

        $application->loadMissing([
            'student',
            'task',
        ]);

        $student = $application->student;

        $taskTitle = $application->task?->title ?? 'مهمة شركة';

        $title = 'تم قبولك في المهمة';

        $body = "تم قبولك رسمياً في مهمة {$taskTitle}. "
            .'يمكنك الآن بدء التنفيذ والتواصل مع الشركة.';

        $data = [
            'priority' => 'high',
            'decision' => 'accepted',
            'application_id' => $application->id,
            'company_task_id' => $application->company_task_id,
            'assignment_id' => $assignment->id,
            'screen' => 'active_task',
        ];

        // In-App + Realtime
        $this->notificationService->send(
            recipient: $student,
            type: NotificationTypes::COMPANY_TASK_APPLICATION_ACCEPTED,
            title: $title,
            body: $body,
            actor: $event->actor,
            related: $assignment,
            data: $data,
        );

        // Push Notification
        SendFirebaseNotificationJob::dispatch(
            recipient: $student,
            title: $title,
            body: $body,
            data: array_map(
                static fn (mixed $value): string => (string) $value,
                $data
            ),
        );
    }
}
