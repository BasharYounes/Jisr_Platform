<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminOpportunityIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('search')) {
            return;
        }

        $search = trim((string) $this->input('search'));

        $this->merge([
            'search' => $search === '' ? null : $search,
        ]);
    }

    public function rules(): array
    {
        return [
            'status' => [
                'nullable',
                'string',
                Rule::in([
                    'draft',
                    'published',
                    'closed',
                    'cancelled',
                ]),
            ],
            'type' => [
                'nullable',
                'string',
                Rule::in([
                    'job',
                    'internship',
                ]),
            ],
            'search' => [
                'nullable',
                'string',
                'max:255',
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
            'status.string' => 'حالة الفرصة يجب أن تكون نصاً. | Opportunity status must be a string.',
            'status.in' => 'حالة الفرصة غير صالحة. | The selected opportunity status is invalid.',

            'type.string' => 'نوع الفرصة يجب أن يكون نصاً. | Opportunity type must be a string.',
            'type.in' => 'نوع الفرصة غير صالح. | The selected opportunity type is invalid.',

            'search.string' => 'قيمة البحث يجب أن تكون نصاً. | Search value must be a string.',
            'search.max' => 'قيمة البحث يجب ألا تتجاوز 255 حرفاً. | Search value must not exceed 255 characters.',

            'page.integer' => 'رقم الصفحة يجب أن يكون عدداً صحيحاً. | Page must be an integer.',
            'page.min' => 'رقم الصفحة يجب أن يكون 1 على الأقل. | Page must be at least 1.',

            'per_page.integer' => 'عدد العناصر في الصفحة يجب أن يكون عدداً صحيحاً. | Per page must be an integer.',
            'per_page.min' => 'عدد العناصر في الصفحة يجب أن يكون 1 على الأقل. | Per page must be at least 1.',
            'per_page.max' => 'عدد العناصر في الصفحة يجب ألا يتجاوز 100. | Per page must not exceed 100.',
        ];
    }
}
