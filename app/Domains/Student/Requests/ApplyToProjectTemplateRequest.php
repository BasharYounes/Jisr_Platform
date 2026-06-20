<?php

namespace App\Domains\Student\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApplyToProjectTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'message.string' => 'رسالة التقديم يجب أن تكون نصاً صحيحاً. | Application message must be a valid text.',
            'message.max' => 'رسالة التقديم يجب ألا تتجاوز 1000 حرف. | Application message must not exceed 1000 characters.',
        ];
    }
}
