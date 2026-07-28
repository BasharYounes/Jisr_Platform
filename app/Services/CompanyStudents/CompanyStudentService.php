<?php

namespace App\Services\CompanyStudents;

use App\Interfaces\CompanyStudentRepositoryInterface;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CompanyStudentService
{
    public function __construct(
        private readonly CompanyStudentRepositoryInterface $studentRepository
    ) {}

    public function search(array $filters): LengthAwarePaginator
    {
        return $this->studentRepository->search(
            filters: $filters,
            perPage: (int) ($filters['per_page'] ?? 10)
        );
    }

    public function getDetails(int $studentId): User
    {
        return $this->studentRepository
            ->findDetailsOrFail($studentId);
    }
}
