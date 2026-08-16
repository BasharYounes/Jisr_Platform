<?php

namespace App\Http\Requests\Admin;

use App\Enums\ComplaintContextType;
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
            'context_type' => [
                'nullable',
                'string',
                Rule::enum(ComplaintContextType::class),
            ],
            'target_type' => [
                'nullable',
                'string',
                Rule::in(['user', 'mentor']),
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
            'context_type.string' => 'سياق الشكوى يجب أن يكون نصاً. | Complaint context must be a string.',
            'context_type.enum' => 'سياق الشكوى غير صالح. | The selected complaint context is invalid.',
            'target_type.string' => 'نوع الطرف المشتكى عليه يجب أن يكون نصاً. | Complaint target type must be a string.',
            'target_type.in' => 'نوع الطرف المشتكى عليه غير صالح. | The selected complaint target type is invalid.',
            'page.integer' => 'رقم الصفحة يجب أن يكون عدداً صحيحاً. | Page must be an integer.',
            'page.min' => 'رقم الصفحة يجب أن يكون 1 على الأقل. | Page must be at least 1.',
            'per_page.integer' => 'عدد العناصر في الصفحة يجب أن يكون عدداً صحيحاً. | Per page must be an integer.',
            'per_page.min' => 'عدد العناصر في الصفحة يجب أن يكون 1 على الأقل. | Per page must be at least 1.',
            'per_page.max' => 'عدد العناصر في الصفحة يجب ألا يتجاوز 100. | Per page must not exceed 100.',
        ];
    }
}
