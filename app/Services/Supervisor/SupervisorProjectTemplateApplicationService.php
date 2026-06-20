<?php

namespace App\Services\Supervisor;

use App\Domains\Supervisor\Support\ProjectTemplateAuthorization;
use App\Models\ProjectTemplate;
use App\Models\ProjectTemplateApplication;
use Illuminate\Database\Eloquent\Collection;

class SupervisorProjectTemplateApplicationService
{
    public function getTemplateApplications(
        ProjectTemplate $projectTemplate,
        int $supervisorId
    ): Collection {
        ProjectTemplateAuthorization::ensureCreator($projectTemplate, $supervisorId);

        return ProjectTemplateApplication::query()
            ->with(['student', 'projectAssignment'])
            ->where('project_template_id', $projectTemplate->id)
            ->latest('applied_at')
            ->get();
    }

    public function getApplicationDetails(
        ProjectTemplateApplication $application,
        int $supervisorId
    ): ProjectTemplateApplication {
        $application->load(['projectTemplate', 'student', 'projectAssignment']);

        ProjectTemplateAuthorization::ensureCreator(
            $application->projectTemplate,
            $supervisorId
        );

        return $application;
    }
}
