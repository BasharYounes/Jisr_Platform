<?php

namespace App\Domains\Student\Actions;

use App\Domains\Student\Enums\ProjectTemplateApplicationStatus;
use App\Models\ProjectTemplate;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ListStudentProjectTemplatesAction
{
    public function execute(
        User $student,
        array $filters
    ): LengthAwarePaginator {
        $query = ProjectTemplate::query()
            ->select([
                'id',
                'title',
                'description',
                'level',
                'expected_outcome',
                'max_students',
                'created_by_type',
                'created_by_id',
                'created_at',
                'updated_at',
            ])
            ->where('created_by_type', 'supervisor')
            ->with([
                'creator:id,name',
                'creator.supervisorProfile:id,user_id,specialization,is_volunteer',

                'applications' => function (HasMany $query) use ($student): void {
                    $query
                        ->select([
                            'id',
                            'project_template_id',
                            'student_user_id',
                            'project_assignment_id',
                            'status',
                            'applied_at',
                        ])
                        ->where('student_user_id', $student->id);
                },
            ])
            ->withCount('tasks')
            ->withCount([
                'applications as active_applications_count' => function (Builder $query): void {
                    $query->whereIn('status', [
                        ProjectTemplateApplicationStatus::PENDING->value,
                        ProjectTemplateApplicationStatus::ACCEPTED->value,
                    ]);
                },
            ])
            ->withSum(
                'tasks as estimated_total_hours',
                'estimated_hours'
            );

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);

            $query->where(function (Builder $query) use ($search): void {
                $query
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('expected_outcome', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['level'])) {
            $query->where('level', $filters['level']);
        }

        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(
                (int) ($filters['per_page'] ?? 15)
            )
            ->withQueryString();
    }
}
