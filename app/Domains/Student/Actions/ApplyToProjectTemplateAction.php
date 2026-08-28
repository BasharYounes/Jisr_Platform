<?php

namespace App\Domains\Student\Actions;

use App\Domains\Student\Enums\ProjectTemplateApplicationStatus;
use App\Models\ProjectTemplate;
use App\Models\ProjectTemplateApplication;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApplyToProjectTemplateAction
{
    public function execute(
        ProjectTemplate $projectTemplate,
        int $studentUserId,
        array $data
    ): ProjectTemplateApplication {
        return DB::transaction(function () use ($projectTemplate, $studentUserId, $data) {
            /*
             * Lock the template row so concurrent applications cannot both pass
             * the max_students check.
             *
             * Also restrict this flow to templates genuinely created through the
             * supervisor domain.
             */
            $template = ProjectTemplate::query()
                ->whereKey($projectTemplate->id)
                ->where('created_by_type', 'supervisor')
                ->lockForUpdate()
                ->firstOrFail();

            $alreadyApplied = ProjectTemplateApplication::query()
                ->where('project_template_id', $template->id)
                ->where('student_user_id', $studentUserId)
                ->exists();

            if ($alreadyApplied) {
                throw ValidationException::withMessages([
                    'project_template' => [
                        'لقد قمت بالتقديم على هذا المشروع مسبقاً. | You have already applied to this project.',
                    ],
                ]);
            }

            if (! is_null($template->max_students)) {
                $activeApplicationsCount = ProjectTemplateApplication::query()
                    ->where('project_template_id', $template->id)
                    ->whereIn('status', [
                        ProjectTemplateApplicationStatus::PENDING->value,
                        ProjectTemplateApplicationStatus::ACCEPTED->value,
                    ])
                    ->count();

                if ($activeApplicationsCount >= $template->max_students) {
                    throw ValidationException::withMessages([
                        'project_template' => [
                            'تم الوصول إلى الحد الأقصى لعدد الطلاب المتقدمين على هذا المشروع. | The maximum number of applicants for this project has been reached.',
                        ],
                    ]);
                }
            }

            return ProjectTemplateApplication::create([
                'project_template_id' => $template->id,
                'student_user_id' => $studentUserId,
                'message' => $data['message'] ?? null,
                'status' => ProjectTemplateApplicationStatus::PENDING->value,
                'applied_at' => now(),
            ]);
        });
    }
}
