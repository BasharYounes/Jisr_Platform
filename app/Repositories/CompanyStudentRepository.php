<?php

namespace App\Repositories;

use App\Interfaces\CompanyStudentRepositoryInterface;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class CompanyStudentRepository implements CompanyStudentRepositoryInterface
{
    public function search(
        array $filters,
        int $perPage = 10
    ): LengthAwarePaginator {
        return User::query()
            ->role('student')
            ->whereHas('studentProfile')

            // Search by student name
            ->when(
                $filters['name'] ?? null,
                fn (Builder $query, string $name) => $query->where(
                    'name',
                    'like',
                    '%'.trim($name).'%'
                )
            )

            // Filter by a skill owned by the student
            ->when(
                $filters['skill_id'] ?? null,
                fn (
                    Builder $query,
                    int|string $skillId
                ) => $query->whereHas(
                    'skills',
                    fn (Builder $skillQuery) => $skillQuery->where(
                        'skills.id',
                        (int) $skillId
                    )
                )
            )

            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findDetailsOrFail(int $studentId): User
    {
        return User::query()
            ->role('student')
            ->whereHas('studentProfile')
            ->whereKey($studentId)
            ->with([
                'studentProfile',

                'skills' => fn ($query) => $query
                    ->orderBy('skills.name'),

                'cvs' => fn ($query) => $query
                    ->orderByDesc('IsPrimary')
                    ->orderByDesc('UploadedAt'),

                'portfolioProjects' => fn ($query) => $query
                    ->orderByDesc('completion_date'),
            ])
            ->firstOrFail();
    }
}
