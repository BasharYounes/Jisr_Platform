<?php

namespace App\Domains\Supervisor\Actions;

use App\Models\ProjectEvaluation;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListMyProjectEvaluationsAction
{
    public function execute(
        User $supervisor,
        array $filters
    ): LengthAwarePaginator {
        $perPage = (int) ($filters['per_page'] ?? 15);

        return ProjectEvaluation::query()
            ->where(
                'supervisor_id',
                $supervisor->id
            )
            ->when(
                $filters['status'] ?? null,
                fn ($query, $status) => $query->where(
                    'status',
                    $status
                )
            )
            ->with([
                'student:id,name,email',
                'assignment.projectTemplate',
                'latestRevisionRequest.requestedBy:id,name,email',
            ])
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }
}
