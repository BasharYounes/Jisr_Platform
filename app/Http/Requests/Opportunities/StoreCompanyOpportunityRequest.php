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
                'required',
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
                    'الحد الأعلى للراتب يجب أن يكون أكبر من أو يساوي الحد الأدنى.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'title.required' => 'عنوان الفرصة مطلوب.',
            'title.string' => 'عنوان الفرصة يجب أن يكون نصاً.',
            'title.max' => 'عنوان الفرصة يجب ألا يتجاوز 255 حرفاً.',

            'description.required' => 'وصف الفرصة مطلوب.',
            'description.string' => 'وصف الفرصة يجب أن يكون نصاً.',

            'type.required' => 'نوع الفرصة مطلوب.',
            'type.string' => 'نوع الفرصة يجب أن يكون نصاً.',
            'type.in' => 'نوع الفرصة غير صالح. القيم المسموحة هي: وظيفة أو تدريب.',

            'location.string' => 'الموقع يجب أن يكون نصاً.',
            'location.max' => 'الموقع يجب ألا يتجاوز 255 حرفاً.',

            'salary_min.numeric' => 'الحد الأدنى للراتب يجب أن يكون رقماً.',
            'salary_min.min' => 'الحد الأدنى للراتب لا يمكن أن يكون سالباً.',

            'salary_max.numeric' => 'الحد الأعلى للراتب يجب أن يكون رقماً.',
            'salary_max.min' => 'الحد الأعلى للراتب لا يمكن أن يكون سالباً.',

            'deadline.date' => 'موعد انتهاء التقديم يجب أن يكون تاريخاً صالحاً.',
            'deadline.after' => 'موعد انتهاء التقديم يجب أن يكون في المستقبل.',

            'skills.array' => 'المهارات يجب أن ترسل كمصفوفة.',

            'skills.*.skill_id.required_with' => 'معرّف المهارة مطلوب عند إرسال المهارات.',
            'skills.*.skill_id.integer' => 'معرّف المهارة يجب أن يكون رقماً صحيحاً.',
            'skills.*.skill_id.distinct' => 'لا يمكن تكرار نفس المهارة أكثر من مرة.',
            'skills.*.skill_id.exists' => 'إحدى المهارات المحددة غير موجودة في النظام.',

            'skills.*.required_level.integer' => 'مستوى المهارة يجب أن يكون رقماً صحيحاً.',
            'skills.*.required_level.min' => 'مستوى المهارة يجب ألا يقل عن 1.',
            'skills.*.required_level.max' => 'مستوى المهارة يجب ألا يتجاوز 100.',

            'skills.*.mandatory.boolean' => 'قيمة إلزامية المهارة يجب أن تكون true أو false.',

            'skills.*.weight.numeric' => 'وزن المهارة يجب أن يكون رقماً.',
            'skills.*.weight.min' => 'وزن المهارة يجب ألا يقل عن 0.01.',
            'skills.*.weight.max' => 'وزن المهارة يجب ألا يتجاوز 9.99.',
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'عنوان الفرصة',
            'description' => 'وصف الفرصة',
            'type' => 'نوع الفرصة',
            'location' => 'الموقع',
            'salary_min' => 'الحد الأدنى للراتب',
            'salary_max' => 'الحد الأعلى للراتب',
            'deadline' => 'موعد انتهاء التقديم',
            'skills' => 'المهارات',
            'skills.*.skill_id' => 'المهارة',
            'skills.*.required_level' => 'مستوى المهارة',
            'skills.*.mandatory' => 'إلزامية المهارة',
            'skills.*.weight' => 'وزن المهارة',
        ];
    }
}
