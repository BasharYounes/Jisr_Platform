<?php

namespace App\Domains\Student\Actions;

use App\Domains\Student\Enums\ProjectTemplateApplicationStatus;
use App\Models\ProjectTemplate;
use App\Models\ProjectTemplateApplication;
use DomainException;
use Illuminate\Support\Facades\DB;

class ApplyToProjectTemplateAction
{
    public function execute(
        ProjectTemplate $projectTemplate,
        int $studentUserId,
        array $data
    ): ProjectTemplateApplication {
        return DB::transaction(function () use ($projectTemplate, $studentUserId, $data) {
            $template = ProjectTemplate::query()
                ->whereKey($projectTemplate->id)
                ->lockForUpdate()
                ->firstOrFail();

            $alreadyApplied = ProjectTemplateApplication::query()
                ->where('project_template_id', $template->id)
                ->where('student_user_id', $studentUserId)
                ->exists();

            if ($alreadyApplied) {
                throw new DomainException(
                    'لقد قمت بالتقديم على هذا المشروع مسبقاً. | You have already applied to this project.'
                );
            }

            if (! is_null($template->max_students)) {
                $activeApplicationsCount = ProjectTemplateApplication::query()
                    ->where('project_template_id', $template->id)
                    ->whereIn('status', [
                        ProjectTemplateApplicationStatus::PENDING,
                        ProjectTemplateApplicationStatus::ACCEPTED,
                    ])
                    ->count();

                if ($activeApplicationsCount >= $template->max_students) {
                    throw new DomainException(
                        'تم الوصول إلى الحد الأقصى لعدد الطلاب المتقدمين على هذا المشروع. | The maximum number of applicants for this project has been reached.'
                    );
                }
            }

            return ProjectTemplateApplication::create([
                'project_template_id' => $template->id,
                'student_user_id' => $studentUserId,
                'message' => $data['message'] ?? null,
                'status' => ProjectTemplateApplicationStatus::PENDING,
                'applied_at' => now(),
            ]);
        });
    }
}
