<?php

namespace App\Domains\Student\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectEvaluationAppealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => [
                'bail',
                'required',
                'string',
                'min:10',
                'max:3000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'The appeal reason is required.',

            'reason.min' => 'The appeal reason must contain at least 10 characters.',

            'reason.max' => 'The appeal reason may not exceed 3000 characters.',
        ];
    }
}
