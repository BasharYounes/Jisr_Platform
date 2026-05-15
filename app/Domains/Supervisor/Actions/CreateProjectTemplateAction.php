<?php

namespace App\Domains\Supervisor\Actions;

use App\Models\ProjectTemplate;

class CreateProjectTemplateAction
{
    public function execute(array $data): ProjectTemplate
    {
        $data['created_by_type'] = 'supervisor';
        $data['created_by_id'] = auth()->id();
        return ProjectTemplate::create($data);
    }
}
