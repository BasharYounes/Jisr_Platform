<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\ProjectAssignment;

class ProjectAssignmentObserver
{
    // public function created(ProjectAssignment $projectAssignment): void
    // {
    //     AuditLog::create([
    //         'user_id' => auth()->id(),
    //         'action' => 'created',
    //         'entity_type' => ProjectAssignment::class,
    //         'entity_id' => $projectAssignment->id,
    //         'old_value' => null,
    //         'new_value' => $projectAssignment->toArray(),
    //         'created_at' => now(),
    //     ]);
    // }

    // public function updated(ProjectAssignment $projectAssignment): void
    // {
    //     $changes = $projectAssignment->getChanges();

    //     unset($changes['updated_at']);

    //     if (empty($changes)) {
    //         return;
    //     }

    //     AuditLog::create([
    //         'user_id' => auth()->id(),
    //         'action' => 'updated',
    //         'entity_type' => ProjectAssignment::class,
    //         'entity_id' => $projectAssignment->id,
    //         'old_value' => array_intersect_key(
    //             $projectAssignment->getOriginal(),
    //             $changes
    //         ),
    //         'new_value' => $changes,
    //         'created_at' => now(),
    //     ]);
    // }

    // public function deleted(ProjectAssignment $projectAssignment): void
    // {
    //     AuditLog::create([
    //         'user_id' => auth()->id(),
    //         'action' => 'deleted',
    //         'entity_type' => ProjectAssignment::class,
    //         'entity_id' => $projectAssignment->id,
    //         'old_value' => $projectAssignment->toArray(),
    //         'new_value' => null,
    //         'created_at' => now(),
    //     ]);
    // }
}
