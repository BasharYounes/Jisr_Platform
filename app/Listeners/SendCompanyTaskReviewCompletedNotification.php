<?php

namespace App\Listeners;

use App\Events\CompanyTaskReviewCompleted;
use App\Jobs\SendFirebaseNotificationJob;
use App\Services\Notifications\NotificationService;
use App\Support\NotificationTypes;

final class SendCompanyTaskReviewCompletedNotification
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function handle(
        CompanyTaskReviewCompleted $event
    ): void {
        $review = $event->review;

        $review->loadMissing([
            'student:id,name',
            'submission:id,company_task_assignment_id',
            'assignment.task:id,title',
        ]);

        $student = $review->student;
        $assignment = $review->assignment;
        $submission = $review->submission;
        $task = $assignment?->task;

        if (
            $student === null
            || $assignment === null
            || $submission === null
            || $task === null
        ) {
            return;
        }

        $title = 'تم تقييم مهمتك';

        $body = "قامت الشركة بتقييم أدائك في مهمة {$task->title}. "
            .'يمكنك الآن عرض التقييم والملاحظات.';

        $data = [
            'priority' => 'high',
            'decision' => $review->final_decision,
            'review_id' => $review->id,
            'submission_id' => $submission->id,
            'assignment_id' => $assignment->id,
            'company_task_id' => $task->id,
            'screen' => 'task_evaluation',
        ];

        // In-App + Realtime
        $this->notificationService->send(
            recipient: $student,
            type: NotificationTypes::COMPANY_TASK_REVIEW_COMPLETED,
            title: $title,
            body: $body,
            actor: $event->actor,
            related: $review,
            data: $data,
        );

        // Push
        SendFirebaseNotificationJob::dispatch(
            recipient: $student,
            title: $title,
            body: $body,
            data: [
                'type' => NotificationTypes::COMPANY_TASK_REVIEW_COMPLETED,
                ...$data,
            ],
        );
    }
}
