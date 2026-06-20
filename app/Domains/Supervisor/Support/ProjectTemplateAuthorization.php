<?php

namespace App\Domains\Supervisor\Support;

use App\Models\ProjectTemplate;
use DomainException;

class ProjectTemplateAuthorization
{
    public static function ensureCreator(ProjectTemplate $template, int $supervisorId): void
    {
        if ($template->created_by_type !== 'supervisor' || (int) $template->created_by_id !== $supervisorId) {
            throw new DomainException(
                'لا يمكنك إدارة طلبات مشروع لم تقم بإنشائه. | You can only manage applications for projects you created.'
            );
        }
    }
}
