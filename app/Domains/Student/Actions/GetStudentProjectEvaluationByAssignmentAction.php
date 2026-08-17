<?php

namespace App\Domains\Student\Actions;

use App\Models\ProjectAssignment;
use App\Models\ProjectEvaluation;
use App\Models\User;

class GetStudentProjectEvaluationByAssignmentAction
{
    public function execute(
        ProjectAssignment $projectAssignment,
        User $student
    ): ?ProjectEvaluation {
        return ProjectEvaluation::query()
            ->where(
                'project_assignment_id',
                $projectAssignment->id
            )
            ->where(
                'student_id',
                $student->id
            )
            ->with([
                'assignment.projectTemplate',
                'student',
                'supervisor',
                'items.criteria',
                'items.evidences',

                'appeals' => fn ($query) => $query
                    ->where(
                        'student_id',
                        $student->id
                    )
                    ->latest('id'),
            ])
            ->first();
    }
}
