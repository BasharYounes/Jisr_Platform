<?php

namespace App\Http\Requests\Admin;

use App\Enums\MentorApplicationSource;
use App\Enums\MentorApplicationStatus;
use App\Enums\SupervisorSpecialization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminMentorApplicationIndexRequest extends FormRequest
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
                Rule::enum(MentorApplicationStatus::class),
            ],
            'source' => [
                'nullable',
                Rule::enum(MentorApplicationSource::class),
            ],
            'specialization' => [
                'nullable',
                Rule::enum(SupervisorSpecialization::class),
            ],
            'search' => [
                'nullable',
                'string',
                'max:255',
            ],
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
            'page' => [
                'nullable',
                'integer',
                'min:1',
            ],
        ];
    }
}
