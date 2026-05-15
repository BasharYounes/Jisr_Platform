<?php

namespace App\Domains\Supervisor\Actions;

use App\Models\ProjectTask;
use App\Models\ProjectTemplate;

class CreateProjectTaskAction
{
    public function execute(ProjectTemplate $template, array $data): ProjectTask
    {
        return $template->tasks()->create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => 'todo',
            'estimated_hours' => $data['estimated_hours'] ?? null,
            'github_branch_or_link' => $data['github_branch_or_link'] ?? null,
            'order_index' => $data['order_index'] ?? 0,
        ]);
    }
}
