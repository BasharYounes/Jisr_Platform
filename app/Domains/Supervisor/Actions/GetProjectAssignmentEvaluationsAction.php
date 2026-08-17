<?php

namespace App\Domains\Supervisor\Actions;

use App\Models\ProjectAssignment;
use Illuminate\Database\Eloquent\Collection;

final class GetProjectAssignmentEvaluationsAction
{
    public function execute(ProjectAssignment $projectAssignment): Collection
    {
        $activeStudentIds = $projectAssignment
            ->members()
            ->where('status', 'active')
            ->pluck('student_id');

        if ($activeStudentIds->isEmpty()) {
            return new Collection;
        }

        return $projectAssignment
            ->evaluations()
            ->whereIn('student_id', $activeStudentIds)
            ->with([
                'student:id,name,email',
                'supervisor:id,name,email',
            ])
            ->orderBy('student_id')
            ->get();
    }
}
