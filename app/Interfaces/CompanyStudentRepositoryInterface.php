<?php

namespace App\Interfaces;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CompanyStudentRepositoryInterface
{
    public function search(
        array $filters,
        int $perPage = 10
    ): LengthAwarePaginator;

    public function findDetailsOrFail(int $studentId): User;
}
