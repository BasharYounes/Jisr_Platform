<?php

namespace App\Http\Requests\Opportunities;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCompanyOpportunityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => [
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'type' => [
                'required',
                'string',
                Rule::in([
                    'job',
                    'internship',
                ]),
            ],

            'location' => [
                'nullable',
                'string',
                'max:255',
            ],

            'salary_min' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'salary_max' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'deadline' => [
                'nullable',
                'date',
                'after:now',
            ],

            'skills' => [
                'nullable',
                'array',
            ],

            'skills.*.skill_id' => [
                'required_with:skills',
                'integer',
                'distinct',
                'exists:skills,id',
            ],

            'skills.*.required_level' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],

            'skills.*.mandatory' => [
                'nullable',
                'boolean',
            ],

            'skills.*.weight' => [
                'nullable',
                'numeric',
                'min:0.01',
                'max:9.99',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $salaryMin = $this->input('salary_min');
            $salaryMax = $this->input('salary_max');

            if (
                $salaryMin !== null
                && $salaryMax !== null
                && (float) $salaryMax < (float) $salaryMin
            ) {
                $validator->errors()->add(
                    'salary_max',
                    'الحد الأعلى للراتب يجب أن يكون أكبر من أو يساوي الحد الأدنى. | Salary max must be greater than or equal to salary min.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'title.required' => 'عنوان الفرصة مطلوب. | Opportunity title is required.',
            'title.string' => 'عنوان الفرصة يجب أن يكون نصاً. | Opportunity title must be a string.',
            'title.max' => 'عنوان الفرصة يجب ألا يتجاوز 255 حرفاً. | Opportunity title must not exceed 255 characters.',

            'description.string' => 'وصف الفرصة يجب أن يكون نصاً. | Opportunity description must be a string.',

            'type.required' => 'نوع الفرصة مطلوب. | Opportunity type is required.',
            'type.string' => 'نوع الفرصة يجب أن يكون نصاً. | Opportunity type must be a string.',
            'type.in' => 'نوع الفرصة غير صالح. القيم المسموحة: job أو internship. | Invalid opportunity type. Allowed values: job or internship.',

            'location.string' => 'الموقع يجب أن يكون نصاً. | Location must be a string.',
            'location.max' => 'الموقع يجب ألا يتجاوز 255 حرفاً. | Location must not exceed 255 characters.',

            'salary_min.numeric' => 'الحد الأدنى للراتب يجب أن يكون رقماً. | Salary min must be numeric.',
            'salary_min.min' => 'الحد الأدنى للراتب لا يمكن أن يكون سالباً. | Salary min cannot be negative.',

            'salary_max.numeric' => 'الحد الأعلى للراتب يجب أن يكون رقماً. | Salary max must be numeric.',
            'salary_max.min' => 'الحد الأعلى للراتب لا يمكن أن يكون سالباً. | Salary max cannot be negative.',

            'deadline.date' => 'موعد انتهاء التقديم يجب أن يكون تاريخاً صالحاً. | Deadline must be a valid date.',
            'deadline.after' => 'موعد انتهاء التقديم يجب أن يكون في المستقبل. | Deadline must be in the future.',

            'skills.array' => 'المهارات يجب أن ترسل كمصفوفة. | Skills must be an array.',

            'skills.*.skill_id.required_with' => 'معرّف المهارة مطلوب عند إرسال المهارات. | Skill ID is required when skills are provided.',
            'skills.*.skill_id.integer' => 'معرّف المهارة يجب أن يكون رقماً صحيحاً. | Skill ID must be an integer.',
            'skills.*.skill_id.distinct' => 'لا يمكن تكرار نفس المهارة أكثر من مرة. | The same skill cannot be duplicated.',
            'skills.*.skill_id.exists' => 'إحدى المهارات المحددة غير موجودة. | One of the selected skills does not exist.',

            'skills.*.required_level.integer' => 'مستوى المهارة يجب أن يكون رقماً صحيحاً. | Required level must be an integer.',
            'skills.*.required_level.min' => 'مستوى المهارة يجب ألا يقل عن 1. | Required level must be at least 1.',
            'skills.*.required_level.max' => 'مستوى المهارة يجب ألا يتجاوز 100. | Required level must not exceed 100.',

            'skills.*.mandatory.boolean' => 'قيمة mandatory يجب أن تكون true أو false. | Mandatory must be true or false.',

            'skills.*.weight.numeric' => 'وزن المهارة يجب أن يكون رقماً. | Skill weight must be numeric.',
            'skills.*.weight.min' => 'وزن المهارة يجب ألا يقل عن 0.01. | Skill weight must be at least 0.01.',
            'skills.*.weight.max' => 'وزن المهارة يجب ألا يتجاوز 9.99. | Skill weight must not exceed 9.99.',
        ];
    }
}
