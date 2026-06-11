<?php

namespace App\Domains\Supervisor\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateProjectTaskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'estimated_hours' => ['nullable', 'integer', 'min:1'],
            'github_branch_or_link' => ['nullable', 'string', 'max:2048'],
            'order_index' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
