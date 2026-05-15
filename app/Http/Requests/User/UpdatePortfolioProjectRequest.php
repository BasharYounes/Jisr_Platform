<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePortfolioProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],

            'description' => ['nullable', 'string'],

            'completion_date' => ['nullable', 'date'],

            'grade' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.string' => 'عنوان المشروع يجب أن يكون نصاً صحيحاً. | Project title must be a valid text.',
            'title.max' => 'عنوان المشروع يجب ألا يتجاوز 255 حرفاً. | Project title must not exceed 255 characters.',

            'description.string' => 'وصف المشروع يجب أن يكون نصاً صحيحاً. | Project description must be a valid text.',

            'completion_date.date' => 'تاريخ الإكمال يجب أن يكون تاريخاً صحيحاً. | Completion date must be a valid date.',

            'grade.numeric' => 'التقييم يجب أن يكون رقماً. | Grade must be a number.',
            'grade.min' => 'التقييم يجب ألا يكون أقل من 0. | Grade must be at least 0.',
            'grade.max' => 'التقييم يجب ألا يتجاوز 100. | Grade must not exceed 100.',
        ];
    }
}