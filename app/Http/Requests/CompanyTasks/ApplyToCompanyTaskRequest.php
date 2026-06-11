<?php

namespace App\Http\Requests\CompanyTasks;

use Illuminate\Foundation\Http\FormRequest;

class ApplyToCompanyTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => ['nullable', 'string', 'max:1000'],

            'portfolio_url' => ['nullable', 'url', 'max:2048'],

            'github_url' => ['nullable', 'url', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'message.string' => 'رسالة التقديم يجب أن تكون نصاً صحيحاً. | Application message must be a valid text.',
            'message.max' => 'رسالة التقديم يجب ألا تتجاوز 1000 حرف. | Application message must not exceed 1000 characters.',

            'portfolio_url.url' => 'رابط البورتفوليو يجب أن يكون رابطاً صحيحاً. | Portfolio URL must be a valid URL.',
            'portfolio_url.max' => 'رابط البورتفوليو يجب ألا يتجاوز 2048 حرفاً. | Portfolio URL must not exceed 2048 characters.',

            'github_url.url' => 'رابط GitHub يجب أن يكون رابطاً صحيحاً. | GitHub URL must be a valid URL.',
            'github_url.max' => 'رابط GitHub يجب ألا يتجاوز 2048 حرفاً. | GitHub URL must not exceed 2048 characters.',
        ];
    }
}
