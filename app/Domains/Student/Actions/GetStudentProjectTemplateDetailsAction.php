<?php

namespace App\Domains\Student\Actions;

use App\Domains\Student\Enums\ProjectTemplateApplicationStatus;
use App\Models\ProjectTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GetStudentProjectTemplateDetailsAction
{
    public function execute(
        ProjectTemplate $projectTemplate,
        User $student
    ): ProjectTemplate {
        return ProjectTemplate::query()
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
            ->whereKey($projectTemplate->id)
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

                'tasks' => function (HasMany $query): void {
                    $query
                        ->select([
                            'id',
                            'project_template_id',
                            'title',
                            'description',
                            'estimated_hours',
                            'order_index',
                        ])
                        ->orderBy('order_index')
                        ->orderBy('id');
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
            )
            ->firstOrFail();
    }
}
