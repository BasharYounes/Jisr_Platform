<?php

namespace App\Domains\Supervisor\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChangeSupervisorAccessStatusRequest extends FormRequest
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
            'reason.required' =>
                'The access status change reason is required.',

            'reason.min' =>
                'The reason must contain at least 10 characters.',

            'reason.max' =>
                'The reason may not exceed 3000 characters.',
        ];
    }
}
