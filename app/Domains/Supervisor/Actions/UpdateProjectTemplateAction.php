<?php

namespace App\Domains\Supervisor\Actions;

use App\Domains\Supervisor\Support\ProjectTemplateAuthorization;
use App\Models\ProjectTemplate;
use DomainException;
use Illuminate\Support\Facades\DB;

class UpdateProjectTemplateAction
{
    public function execute(
        ProjectTemplate $projectTemplate,
        int $supervisorId,
        array $data
    ): ProjectTemplate {
        return DB::transaction(function () use (
            $projectTemplate,
            $supervisorId,
            $data
        ) {
            $template = ProjectTemplate::query()
                ->whereKey($projectTemplate->id)
                ->lockForUpdate()
                ->firstOrFail();

            ProjectTemplateAuthorization::ensureCreator(
                $template,
                $supervisorId
            );

            $hasAssignment = $template->assignments()->exists();

            $hasBlockingApplication = $template->applications()
                ->whereIn('status', ['pending', 'accepted'])
                ->exists();

            if ($hasAssignment || $hasBlockingApplication) {
                throw new DomainException(
                    'لا يمكن تعديل قالب مشروع دخل مرحلة التقديم أو التنفيذ. ' .
                    '| A project template cannot be edited after applications or assignments exist.'
                );
            }

            $template->fill($data);
            $template->save();

            return $template->refresh();
        });
    }
}
