<?php

namespace App\Http\Requests\CompanyStudents;

use Illuminate\Foundation\Http\FormRequest;

class SearchCompanyStudentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge([
                'name' => trim((string) $this->input('name')),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'skill_id' => [
                'nullable',
                'integer',
                'exists:skills,id',
            ],

            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:50',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.string' => 'اسم الطالب يجب أن يكون نصاً.',

            'name.max' => 'اسم الطالب يجب ألا يتجاوز 255 محرفاً.',

            'skill_id.integer' => 'معرّف المهارة يجب أن يكون رقماً صحيحاً.',

            'skill_id.exists' => 'المهارة المحددة غير موجودة.',

            'per_page.integer' => 'عدد العناصر في الصفحة يجب أن يكون رقماً صحيحاً.',

            'per_page.min' => 'عدد العناصر في الصفحة يجب ألا يقل عن 1.',

            'per_page.max' => 'عدد العناصر في الصفحة يجب ألا يتجاوز 50.',
        ];
    }
}
