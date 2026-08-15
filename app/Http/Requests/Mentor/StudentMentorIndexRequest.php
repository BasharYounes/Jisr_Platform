<?php

namespace App\Http\Requests\Mentor;

use App\Enums\SupervisorSpecialization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudentMentorIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('student') ?? false;
    }

    public function rules(): array
    {
        return [
            'search' => [
                'nullable',
                'string',
                'max:255',
            ],
            'specialization' => [
                'nullable',
                Rule::enum(SupervisorSpecialization::class),
            ],
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:50',
            ],
            'page' => [
                'nullable',
                'integer',
                'min:1',
            ],
        ];
    }
}
