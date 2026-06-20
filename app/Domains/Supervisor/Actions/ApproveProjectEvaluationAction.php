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
            if (
                $evaluation->status !==
                ProjectEvaluationStatus::SUBMITTED->value
            ) {
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

            $evaluation->update([
                'status' => ProjectEvaluationStatus::APPROVED->value,
            ]);

            /*
             * لا نكمل المشروع عند اعتماد تقييم طالب واحد.
             * يكتمل فقط بعد اعتماد تقييم جميع أعضاء الفريق النشطين.
             */
            $activeStudentIds = $assignment->members()
                ->where('status', 'active')
                ->pluck('student_id');

            $approvedStudentIds = ProjectEvaluation::query()
                ->where('project_assignment_id', $assignment->id)
                ->whereIn('student_id', $activeStudentIds)
                ->where(
                    'status',
                    ProjectEvaluationStatus::APPROVED->value
                )
                ->pluck('student_id');

            $allActiveStudentsApproved =
                $activeStudentIds->isNotEmpty() &&
                $activeStudentIds->diff($approvedStudentIds)->isEmpty();

            if ($allActiveStudentsApproved) {
                $oldStatus = $assignment->status->value;

                $assignment->update([
                    'status' => ProjectAssignmentStatus::COMPLETED->value,
                ]);

                event(new ProjectAssignmentStatusChanged(
                    assignment: $assignment,
                    oldStatus: $oldStatus,
                    newStatus: ProjectAssignmentStatus::COMPLETED->value,
                    changedBy: auth()->id()
                ));
            }

            return $evaluation->refresh()->load([
                'assignment.projectTemplate',
                'student',
                'supervisor',
                'items.criteria',
            ]);
        });
    }
}
