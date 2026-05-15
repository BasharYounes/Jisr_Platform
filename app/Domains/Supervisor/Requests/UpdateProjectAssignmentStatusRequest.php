<?php

namespace App\Domains\Supervisor\Requests;

use App\Domains\Supervisor\Enums\ProjectAssignmentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateProjectAssignmentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                new Enum(ProjectAssignmentStatus::class),
            ],
        ];
    }
}
