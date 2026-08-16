<?php

namespace App\Domains\Student\Requests;

use App\Domains\Supervisor\Enums\ProjectAssignmentTaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListAssignedProjectTasksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'nullable',
                Rule::enum(ProjectAssignmentTaskStatus::class),
            ],
            'project_assignment_id' => [
                'nullable',
                'integer',
                Rule::exists('project_assignments', 'id'),
            ],
            'page' => [
                'nullable',
                'integer',
                'min:1',
            ],
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ];
    }
}
