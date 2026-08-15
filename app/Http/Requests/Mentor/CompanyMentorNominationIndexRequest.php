<?php

namespace App\Http\Requests\Mentor;

use App\Enums\MentorApplicationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompanyMentorNominationIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('company') ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'nullable',
                Rule::enum(MentorApplicationStatus::class),
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
