<?php

namespace App\Domains\Supervisor\Actions;

use App\Domains\Supervisor\Enums\ProjectAssignmentStatus;
use App\Domains\Supervisor\Enums\ProjectAssignmentTaskStatus;
use App\Domains\Supervisor\Enums\ProjectEvaluationStatus;
use App\Events\ProjectAssignmentStatusChanged;
use App\Models\ProjectEvaluation;
use DomainException;
use Illuminate\Support\Facades\DB;

class ApproveProjectEvaluationAction
{
    public function execute(ProjectEvaluation $evaluation): ProjectEvaluation
    {
        return DB::transaction(function () use ($evaluation) {
            // dd($evaluation->status);
            if ($evaluation->status !== ProjectEvaluationStatus::SUBMITTED->value) {
                throw new DomainException(
                    'Only submitted evaluations can be approved.'
                );
            }

            $assignment = $evaluation->assignment;

            $totalTasks = $assignment->assignmentTasks()->count();

            if ($totalTasks === 0) {
                throw new DomainException(
                    'This project assignment has no tasks to approve.'
                );
            }

            $unfinishedTasks = $assignment->assignmentTasks()
                ->where('status', '!=', ProjectAssignmentTaskStatus::DONE->value)
                ->count();

            if ($unfinishedTasks > 0) {
                throw new DomainException(
                    'Project evaluation can only be approved after all assignment tasks are completed.'
                );
            }

            $oldStatus = $assignment->status->value;

            $evaluation->update([
                'status' => ProjectEvaluationStatus::APPROVED->value,
            ]);

            $assignment->update([
                'status' => ProjectAssignmentStatus::COMPLETED->value,
            ]);

            event(new ProjectAssignmentStatusChanged(
                assignment: $assignment,
                oldStatus: $oldStatus,
                newStatus: ProjectAssignmentStatus::COMPLETED->value,
                changedBy: auth()->id()
            ));

            return $evaluation->refresh()->load([
                'assignment.students',
                'assignment.projectTemplate',
                'supervisor',
                'items.criteria',
            ]);
        });
    }
}
