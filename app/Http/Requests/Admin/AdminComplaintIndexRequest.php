<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminComplaintIndexRequest extends FormRequest
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
                'string',
                Rule::in([
                    'pending',
                    'under_review',
                    'resolved',
                    'rejected',
                ]),
            ],
            'page' => [
                'nullable',
                'integer',
                'min:1',
            ],
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'status.string' => 'حالة الشكوى يجب أن تكون نصاً. | Complaint status must be a string.',
            'status.in' => 'حالة الشكوى غير صالحة. | The selected complaint status is invalid.',

            'page.integer' => 'رقم الصفحة يجب أن يكون عدداً صحيحاً. | Page must be an integer.',
            'page.min' => 'رقم الصفحة يجب أن يكون 1 على الأقل. | Page must be at least 1.',

            'per_page.integer' => 'عدد العناصر في الصفحة يجب أن يكون عدداً صحيحاً. | Per page must be an integer.',
            'per_page.min' => 'عدد العناصر في الصفحة يجب أن يكون 1 على الأقل. | Per page must be at least 1.',
            'per_page.max' => 'عدد العناصر في الصفحة يجب ألا يتجاوز 100. | Per page must not exceed 100.',
        ];
    }
}
