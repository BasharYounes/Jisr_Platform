<?php

namespace App\Listeners;

use App\Events\CompanyOpportunityHighMatchApplicationReceived;
use App\Services\Notifications\NotificationService;
use App\Support\NotificationTypes;

class SendCompanyOpportunityHighMatchApplicationNotification
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function handle(CompanyOpportunityHighMatchApplicationReceived $event): void
    {
        $application = $event->application;

        $application->loadMissing([
            'user',
            'opportunity.company',
        ]);

        $student = $application->user;
        $opportunity = $application->opportunity;
        $company = $opportunity?->company;

        if (! $student || ! $opportunity || ! $company) {
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
                type: NotificationTypes::COMPANY_OPPORTUNITY_HIGH_MATCH_APPLICATION,
                title: 'متقدم مناسب جديد',
                body: "{$student->name} تقدم على فرصة {$opportunity->title} "
                    ."من نوع {$opportunity->type} بنسبة توافق {$matchScore}%.",
                actor: $student,
                related: $application,
                data: [
                    'priority' => 'low',
                    'application_id' => $application->id,
                    'opportunity_id' => $opportunity->id,
                    'opportunity_type' => $opportunity->type,
                    'student_user_id' => $student->id,
                    'match_score' => $matchScore,
                    'screen' => 'opportunity_applications',
                ],
            );
        }
    }
}
