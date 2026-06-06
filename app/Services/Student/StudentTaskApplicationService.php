<?php

namespace App\Services\Student;

use App\Interfaces\StudentTaskApplicationRepositoryInterface;
use Illuminate\Support\Collection;

class StudentTaskApplicationService
{
    public function __construct(
        private readonly StudentTaskApplicationRepositoryInterface $studentTaskApplicationRepository
    ) {}

    public function getAllStudentTaskApplications(int $studentUserId): array
{
    return [
        'applied' => $this->getAppliedTasks($studentUserId),
        'accepted' => $this->getAcceptedTasks($studentUserId),
        'rejected' => $this->getRejectedTasks($studentUserId),
    ];
    }

    public function getAppliedTasks(int $studentUserId): Collection
    {
        return $this->studentTaskApplicationRepository
            ->getApplicationsByStatus($studentUserId, 'pending');
    }

    public function getRejectedTasks(int $studentUserId): Collection
    {
        return $this->studentTaskApplicationRepository
            ->getApplicationsByStatus($studentUserId, 'rejected');
    }

    public function getAcceptedTasks(int $studentUserId): Collection
    {
        return $this->studentTaskApplicationRepository
            ->getAcceptedAssignments($studentUserId);
    }
}