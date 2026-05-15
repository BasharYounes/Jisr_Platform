<?php

namespace App\Listeners;

use App\Events\ProjectAssignmentStatusChanged;
use App\Models\Notification;

class NotifyStudentProjectStatusChanged
{
    public function handle(ProjectAssignmentStatusChanged $event): void
    {
        $assignment = $event->assignment->loadMissing('members');

        foreach ($assignment->members as $member) {
            Notification::create([
                'user_id' => $member->student_id,
                'actor_id' => $event->changedBy,
                'type' => 'project_assignment_status_changed',
                'is_read' => false,
                'created_at' => now(),
            ]);
        }
    }
}
