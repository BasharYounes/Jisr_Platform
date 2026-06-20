<?php

namespace App\Services\Student;

use App\Domains\Student\Enums\ProjectTemplateApplicationStatus;
use App\Models\ProjectTemplateApplication;
use Illuminate\Database\Eloquent\Collection;

class StudentProjectTemplateApplicationService
{
    public function getAllApplications(int $studentUserId): array
    {
        return [
            'pending' => $this->getApplicationsByStatus($studentUserId, ProjectTemplateApplicationStatus::PENDING),
            'accepted' => $this->getApplicationsByStatus($studentUserId, ProjectTemplateApplicationStatus::ACCEPTED),
            'rejected' => $this->getApplicationsByStatus($studentUserId, ProjectTemplateApplicationStatus::REJECTED),
        ];
    }

    public function getApplicationsByStatus(
        int $studentUserId,
        ProjectTemplateApplicationStatus $status
    ): Collection {
        return ProjectTemplateApplication::query()
            ->with(['projectTemplate', 'projectAssignment'])
            ->where('student_user_id', $studentUserId)
            ->where('status', $status)
            ->latest('applied_at')
            ->get();
    }
}
