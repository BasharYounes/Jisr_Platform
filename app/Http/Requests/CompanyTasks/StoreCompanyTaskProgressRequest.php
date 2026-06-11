<?php

namespace App\Http\Requests\CompanyTasks;

use Illuminate\Foundation\Http\FormRequest;

class StoreCompanyTaskProgressRequest extends FormRequest
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
                'max:5000',
            ],

            'progress_percentage' => [
                'required',
                'integer',
                'between:0,100',
            ],

            'github_url' => [
                'nullable',
                'url',
                'max:2048',
            ],

            'demo_url' => [
                'nullable',
                'url',
                'max:2048',
            ],

            'attachments' => [
                'required',
                'array',
                'min:1',
                'max:5',
            ],

            'attachments.*' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,pdf,json,zip',
                'max:10240',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => [
                'ar' => 'عنوان تحديث التقدم مطلوب.',
                'en' => 'Progress update title is required.',
            ],
            'title.string' => [
                'ar' => 'عنوان تحديث التقدم يجب أن يكون نصاً.',
                'en' => 'Progress update title must be a string.',
            ],
            'title.max' => [
                'ar' => 'عنوان تحديث التقدم يجب ألا يتجاوز 255 حرفاً.',
                'en' => 'Progress update title must not exceed 255 characters.',
            ],

            'description.required' => [
                'ar' => 'وصف تحديث التقدم مطلوب.',
                'en' => 'Progress update description is required.',
            ],
            'description.string' => [
                'ar' => 'وصف تحديث التقدم يجب أن يكون نصاً.',
                'en' => 'Progress update description must be a string.',
            ],
            'description.max' => [
                'ar' => 'وصف تحديث التقدم يجب ألا يتجاوز 5000 حرف.',
                'en' => 'Progress update description must not exceed 5000 characters.',
            ],

            'progress_percentage.required' => [
                'ar' => 'نسبة التقدم مطلوبة.',
                'en' => 'Progress percentage is required.',
            ],
            'progress_percentage.integer' => [
                'ar' => 'نسبة التقدم يجب أن تكون رقماً صحيحاً.',
                'en' => 'Progress percentage must be an integer.',
            ],
            'progress_percentage.between' => [
                'ar' => 'نسبة التقدم يجب أن تكون بين 0 و100.',
                'en' => 'Progress percentage must be between 0 and 100.',
            ],

            'github_url.url' => [
                'ar' => 'رابط GitHub غير صالح.',
                'en' => 'The GitHub URL is invalid.',
            ],
            'demo_url.url' => [
                'ar' => 'رابط العرض التجريبي غير صالح.',
                'en' => 'The demo URL is invalid.',
            ],

            'attachments.required' => [
                'ar' => 'يجب إرفاق ملف واحد على الأقل لتوثيق التقدم.',
                'en' => 'At least one progress evidence file is required.',
            ],
            'attachments.array' => [
                'ar' => 'ملفات التوثيق يجب أن تكون ضمن قائمة.',
                'en' => 'Attachments must be an array.',
            ],
            'attachments.min' => [
                'ar' => 'يجب إرفاق ملف توثيق واحد على الأقل.',
                'en' => 'At least one attachment is required.',
            ],
            'attachments.max' => [
                'ar' => 'لا يمكن إرفاق أكثر من 5 ملفات.',
                'en' => 'No more than 5 attachments may be uploaded.',
            ],
            'attachments.*.file' => [
                'ar' => 'كل عنصر ضمن ملفات التوثيق يجب أن يكون ملفاً.',
                'en' => 'Each attachment must be a valid file.',
            ],
            'attachments.*.mimes' => [
                'ar' => 'صيغة ملف التوثيق غير مدعومة.',
                'en' => 'The attachment file type is not supported.',
            ],
            'attachments.*.max' => [
                'ar' => 'حجم كل ملف يجب ألا يتجاوز 10 ميغابايت.',
                'en' => 'Each attachment must not exceed 10 MB.',
            ],
        ];
    }
}
