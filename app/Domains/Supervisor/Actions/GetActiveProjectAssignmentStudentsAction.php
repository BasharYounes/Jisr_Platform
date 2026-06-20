<?php

namespace App\Domains\Supervisor\Actions;

use App\Models\ProjectAssignment;
use DomainException;
use Illuminate\Support\Collection;

class GetActiveProjectAssignmentStudentsAction
{
    public function execute(
        ProjectAssignment $projectAssignment,
        int $supervisorId
    ): Collection {
        if ((int) $projectAssignment->supervisor_id !== $supervisorId) {
            throw new DomainException(
                'لا يمكنك عرض طلاب مشروع لا يتبع لك. | You can only view students in your own project.'
            );
        }

        return $projectAssignment->members()
            ->where('status', 'active')
            ->with('student:id,name,email')
            ->orderBy('id')
            ->get()
            ->map(static function ($member) {
                return [
                    'student_id' => (int) $member->student_id,
                    'name' => $member->student?->name,
                    'email' => $member->student?->email,
                    'role' => $member->role,
                    'status' => $member->status,
                ];
            })
            ->values();
    }
}
