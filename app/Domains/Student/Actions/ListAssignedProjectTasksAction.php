<?php

namespace App\Domains\Student\Actions;

use App\Models\ProjectAssignmentTask;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListAssignedProjectTasksAction
{
    public function execute(
        User $student,
        array $filters
    ): LengthAwarePaginator {
        $query = ProjectAssignmentTask::query()
            ->where('assigned_student_id', $student->id)
            ->with([
                'assignment:id,project_template_id,supervisor_id,status,progress_percentage,assigned_at,submitted_at',
                'assignment.projectTemplate:id,title,level',
                'assignment.supervisor:id,name,email',
            ]);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['project_assignment_id'])) {
            $query->where(
                'project_assignment_id',
                (int) $filters['project_assignment_id']
            );
        }

        return $query
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();
    }
}
