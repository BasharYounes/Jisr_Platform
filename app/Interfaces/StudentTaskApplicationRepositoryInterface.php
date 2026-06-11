<?php

namespace App\Interfaces;

use Illuminate\Support\Collection;

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
