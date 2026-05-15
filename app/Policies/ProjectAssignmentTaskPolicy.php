<?php

namespace App\Policies;

use App\Models\ProjectAssignmentTask;
use App\Models\User;

class ProjectAssignmentTaskPolicy
{
    public function start(User $user, ProjectAssignmentTask $task): bool
    {
        return $task->assigned_student_id === $user->id;
    }

    public function submit(User $user, ProjectAssignmentTask $task): bool
    {
        return $task->assigned_student_id === $user->id;
    }

    public function review(User $user, ProjectAssignmentTask $task): bool
    {
        return $user->hasRole('supervisor') &&
               $task->assignment->supervisor_id === $user->id;
    }

    public function approve(User $user, ProjectAssignmentTask $task): bool
    {
        return $user->hasRole('supervisor') &&
               $task->assignment->supervisor_id === $user->id;
    }

    public function requestRevision(User $user, ProjectAssignmentTask $task): bool
    {
        return $user->hasRole('supervisor') &&
                $task->assignment->supervisor_id === $user->id;
    }
}
