<?php

namespace App\Domains\Supervisor\Requests;

use App\Domains\Supervisor\Enums\ProjectAssignmentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListLeadProjectAssignmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $activeStatuses = [
            ProjectAssignmentStatus::PENDING->value,
            ProjectAssignmentStatus::ASSIGNED->value,
            ProjectAssignmentStatus::IN_PROGRESS->value,
            ProjectAssignmentStatus::SUBMITTED->value,
            ProjectAssignmentStatus::UNDER_REVIEW->value,
        ];

        return [
            'status' => [
                'nullable',
                'string',
                Rule::in($activeStatuses),
            ],
            'supervisor_id' => [
                'nullable',
                'integer',
                'exists:users,id',
            ],
            'search' => [
                'nullable',
                'string',
                'max:255',
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
