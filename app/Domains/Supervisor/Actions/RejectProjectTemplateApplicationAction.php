<?php

namespace App\Domains\Supervisor\Actions;

use App\Domains\Student\Enums\ProjectTemplateApplicationStatus;
use App\Domains\Supervisor\Support\ProjectTemplateAuthorization;
use App\Models\ProjectTemplateApplication;
use DomainException;
use Illuminate\Support\Facades\DB;

class RejectProjectTemplateApplicationAction
{
    public function execute(
        ProjectTemplateApplication $application,
        int $supervisorId,
        array $data = []
    ): ProjectTemplateApplication {
        return DB::transaction(function () use ($application, $supervisorId, $data) {
            $application = ProjectTemplateApplication::query()
                ->with('projectTemplate')
                ->whereKey($application->id)
                ->lockForUpdate()
                ->firstOrFail();

            ProjectTemplateAuthorization::ensureCreator(
                $application->projectTemplate,
                $supervisorId
            );

            if ($application->status !== ProjectTemplateApplicationStatus::PENDING) {
                throw new DomainException(
                    'لا يمكن اتخاذ قرار على طلب تمت مراجعته مسبقاً. | This application has already been reviewed.'
                );
            }

            $application->update([
                'status' => ProjectTemplateApplicationStatus::REJECTED,
                'reviewed_at' => now(),
                'supervisor_notes' => $data['supervisor_notes'] ?? null,
            ]);

            return $application->refresh()->load([
                'projectTemplate',
                'student',
            ]);
        });
    }
}
