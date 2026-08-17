<?php

namespace App\Listeners;

use App\Events\CompanyTaskHighMatchApplicationReceived;
use App\Services\Notifications\NotificationService;
use App\Support\NotificationTypes;

final class SendCompanyTaskHighMatchApplicationNotification
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function handle(
        CompanyTaskHighMatchApplicationReceived $event
    ): void {
        $application = $event->application;

        $application->loadMissing([
            'student',
            'task.company',
        ]);

        $student = $application->student;
        $task = $application->task;
        $company = $task?->company;

        if (! $student || ! $task || ! $company) {
            return;
        }

        $companyUsers = $company->users()
            ->wherePivot('role', 'owner')
            ->get();

        if ($companyUsers->isEmpty()) {
            return;
        }

        $matchScore = (float) $application->match_score;

        foreach ($companyUsers as $recipient) {
            $this->notificationService->send(
                recipient: $recipient,
                type: NotificationTypes::COMPANY_TASK_HIGH_MATCH_APPLICATION,
                title: 'متقدم مناسب جديد',
                body: "{$student->name} تقدم على مهمة {$task->title} "
                    ."بنسبة توافق {$matchScore}%.",
                actor: $student,
                related: $application,
                data: [
                    'priority' => 'low',
                    'application_id' => $application->id,
                    'company_task_id' => $task->id,
                    'student_user_id' => $student->id,
                    'match_score' => $matchScore,
                    'screen' => 'task_applications',
                ],
            );
        }
    }
}
