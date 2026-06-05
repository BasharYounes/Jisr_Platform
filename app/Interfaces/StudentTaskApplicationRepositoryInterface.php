<?php

namespace App\Interfaces;
use Illuminate\Support\Collection;


use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface StudentTaskApplicationRepositoryInterface
{
      public function getApplicationsByStatus(
        int $studentUserId,
        string $status
    ): Collection;

    public function getAcceptedAssignments(
        int $studentUserId
    ): Collection;
}