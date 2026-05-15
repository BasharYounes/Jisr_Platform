<?php

namespace App\Listeners;

use App\Events\ProjectAssignmentReadyForEvaluation;
use App\Models\Notification;

class NotifySupervisorProjectReadyForEvaluation
{
    public function handle(ProjectAssignmentReadyForEvaluation $event): void
    {
        Notification::create([
            'user_id' => $event->assignment->supervisor_id,
            'actor_id' => null,
            'type' => 'project_ready_for_final_evaluation',
            'is_read' => false,
            'created_at' => now(),
        ]);
    }
}
