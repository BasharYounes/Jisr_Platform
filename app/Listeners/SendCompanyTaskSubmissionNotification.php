<?php

namespace App\Listeners;

use App\Events\CompanyTaskSubmissionSubmitted;
use App\Interfaces\CompanyRepositoryInterface;
use App\Jobs\SendFirebaseNotificationJob;
use App\Services\Notifications\NotificationService;
use App\Support\NotificationTypes;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SendCompanyTaskSubmissionNotification implements ShouldQueueAfterCommit
{
    public int $tries = 3;

    public array $backoff = [30, 120];

    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly CompanyRepositoryInterface $companyRepository,
    ) {}

    public function handle(CompanyTaskSubmissionSubmitted $event): void
    {
        $submission = $event->submission;

        $submission->loadMissing([
            'student:id,name',
            'assignment:id,company_task_id',
            'assignment.task:id,company_id,title',
        ]);

        $student = $submission->student;
        $assignment = $submission->assignment;
        $task = $assignment?->task;

        if (! $student || ! $assignment || ! $task) {
            return;
        }

        $company = $this->companyRepository->findById(
            (int) $task->company_id
        );

        $recipients = $company->users->filter(
            static fn ($user): bool => (bool) $user->is_active
        );

        if ($recipients->isEmpty()) {
            Log::warning(
                'Task submission notification skipped because the company has no active owner.',
                [
                    'submission_id' => $submission->id,
                    'company_id' => $company->id,
                ]
            );

            return;
        }

        $title = 'تم استلام تسليم نهائي';

        $body = "سلّم {$student->name} الحل النهائي لمهمة "
            ."{$task->title}. راجع التسليم الآن.";

        $data = [
            'submission_id' => $submission->id,
            'assignment_id' => $assignment->id,
            'company_task_id' => $task->id,
            'student_user_id' => $student->id,
            'screen' => 'company_task_submission',
        ];

        foreach ($recipients as $recipient) {
            // In-App + Realtime
            $this->notificationService->send(
                recipient: $recipient,
                type: NotificationTypes::COMPANY_TASK_SUBMISSION_RECEIVED,
                title: $title,
                body: $body,
                actor: $student,
                related: $submission,
                data: $data,
            );

            // Push Notification
            SendFirebaseNotificationJob::dispatch(
                recipient: $recipient,
                title: $title,
                body: $body,
                data: [
                    'type' => NotificationTypes::COMPANY_TASK_SUBMISSION_RECEIVED,
                    ...$data,
                ],
            );
        }
    }

    public function failed(
        CompanyTaskSubmissionSubmitted $event,
        Throwable $exception
    ): void {
        Log::error(
            'Task submission notification listener failed permanently.',
            [
                'submission_id' => $event->submission->id,
                'error' => $exception->getMessage(),
            ]
        );
    }
}
