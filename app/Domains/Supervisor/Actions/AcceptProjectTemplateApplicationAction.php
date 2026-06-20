<?php

namespace App\Domains\Supervisor\Actions;

use App\Domains\Student\Enums\ProjectTemplateApplicationStatus;
use App\Domains\Supervisor\Support\ProjectTemplateAuthorization;
use App\Models\ProjectTemplateApplication;
use DomainException;
use Illuminate\Support\Facades\DB;

class AcceptProjectTemplateApplicationAction
{
    public function __construct(
        private readonly AssignProjectAction $assignProjectAction
    ) {}

    public function execute(
        ProjectTemplateApplication $application,
        int $supervisorId,
        array $data = []
    ): ProjectTemplateApplication {
        return DB::transaction(function () use ($application, $supervisorId, $data) {
            $application = ProjectTemplateApplication::query()
                ->with(['projectTemplate.tasks', 'student'])
                ->whereKey($application->id)
                ->lockForUpdate()
                ->firstOrFail();

            $template = $application->projectTemplate;

            ProjectTemplateAuthorization::ensureCreator($template, $supervisorId);
            $this->ensureApplicationIsPending($application);

            if ($application->project_assignment_id !== null) {
                throw new DomainException(
                    'يوجد تكليف سابق لهذا الطلب. | An assignment already exists for this application.'
                );
            }

            $assignment = $this->assignProjectAction->execute([
                'project_template_id' => $template->id,
                'students' => [
                    ['student_id' => $application->student_user_id],
                ],
            ]);

            $application->update([
                'status' => ProjectTemplateApplicationStatus::ACCEPTED,
                'reviewed_at' => now(),
                'supervisor_notes' => $data['supervisor_notes'] ?? null,
                'project_assignment_id' => $assignment->id,
            ]);

            return $application->refresh()->load([
                'projectTemplate',
                'student',
                'projectAssignment.supervisor',
            ]);
        });
    }

    private function ensureApplicationIsPending(ProjectTemplateApplication $application): void
    {
        if ($application->status !== ProjectTemplateApplicationStatus::PENDING) {
            throw new DomainException(
                'لا يمكن اتخاذ قرار على طلب تمت مراجعته مسبقاً. | This application has already been reviewed.'
            );
        }
    }
}
