<?php

namespace App\Http\Requests\Opportunities;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexCompanyOpportunityRequest extends FormRequest
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
        ];
    }
}
