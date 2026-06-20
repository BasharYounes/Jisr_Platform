<?php

namespace App\Domains\Supervisor\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReviewProjectTemplateApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supervisor_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'supervisor_notes.string' => 'ملاحظات المشرف يجب أن تكون نصاً. | Supervisor notes must be a valid text.',
            'supervisor_notes.max' => 'ملاحظات المشرف يجب ألا تتجاوز 1000 حرف. | Supervisor notes must not exceed 1000 characters.',
        ];
    }
}
