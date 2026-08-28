<?php

namespace App\Listeners;

use App\Events\ProjectAssignmentStatusChanged;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use App\Support\NotificationTypes;

class NotifyStudentProjectStatusChanged
{
    public function __construct(
        private readonly NotificationService $notifications
    ) {}

    public function handle(
        ProjectAssignmentStatusChanged $event
    ): void {
        $assignment = $event->assignment->loadMissing([
            'members.student',
        ]);

        $actor = User::find($event->changedBy);

        $activeMembers = $assignment->members
            ->where('status', 'active');

        foreach ($activeMembers as $member) {
            $student = $member->student;

            if ($student === null) {
                continue;
            }

            $this->notifications->send(
                recipient: $student,
                type: NotificationTypes::PROJECT_STATUS_CHANGED,
                title: 'تم تحديث حالة مشروعك',
                body: 'قام المشرف بتحديث حالة المشروع.',
                actor: $actor,
                related: $assignment,
                data: [
                    'project_assignment_id' => $assignment->id,
                    'old_status' => $event->oldStatus,
                    'new_status' => $event->newStatus,
                    'screen' => 'project_assignment_details',
                ],
            );
        }
    }
}
