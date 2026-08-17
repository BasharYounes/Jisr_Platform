<?php

namespace App\Domains\Student\Actions;

use App\Models\ProjectEvaluationAppeal;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListStudentEvaluationAppealsAction
{
    public function execute(
        User $student,
        array $filters
    ): LengthAwarePaginator {
        $query = ProjectEvaluationAppeal::query()
            ->where(
                'student_id',
                $student->id
            )
            ->with([
                'reviewedBy:id,name,email',

                'evaluation:id,project_assignment_id,supervisor_id,status,total_score,final_grade,general_comment,evaluated_at,appeal_started_at,appeal_deadline_at',

                'evaluation.assignment:id,project_template_id,status,progress_percentage',

                'evaluation.assignment.projectTemplate:id,title,level',

                'evaluation.supervisor:id,name,email',
            ]);

        if (! empty($filters['status'])) {
            $query->where(
                'status',
                $filters['status']
            );
        }

        if (! empty($filters['project_assignment_id'])) {
            $assignmentId =
                (int) $filters['project_assignment_id'];

            $query->whereHas(
                'evaluation',
                fn ($evaluationQuery) => $evaluationQuery->where(
                    'project_assignment_id',
                    $assignmentId
                )
            );
        }

        return $query
            ->latest('id')
            ->paginate(
                (int) ($filters['per_page'] ?? 15)
            )
            ->withQueryString();
    }
}
