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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],

            'progress_percentage' => ['required', 'integer', 'between:0,100'],

            'github_url' => ['nullable', 'url', 'max:2048'],

            'demo_url' => ['nullable', 'url', 'max:2048'],

            'attachments' => [
                'required', 'array', 'min:1', 'max:5'],

            'attachments.*' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf,json,zip', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'عنوان تحديث التقدم مطلوب. | Progress update title is required.',

            'title.string' => 'عنوان تحديث التقدم يجب أن يكون نصاً. | Progress update title must be a string.',

            'title.max' => 'عنوان تحديث التقدم يجب ألا يتجاوز 255 حرفاً. | Progress update title must not exceed 255 characters.',

            'description.required' => 'وصف تحديث التقدم مطلوب. | Progress update description is required.',

            'description.string' => 'وصف تحديث التقدم يجب أن يكون نصاً. | Progress update description must be a string.',

            'description.max' => 'وصف تحديث التقدم يجب ألا يتجاوز 5000 حرف. | Progress update description must not exceed 5000 characters.',

            'progress_percentage.required' => 'نسبة التقدم مطلوبة. | Progress percentage is required.',

            'progress_percentage.integer' => 'نسبة التقدم يجب أن تكون رقماً صحيحاً. | Progress percentage must be an integer.',

            'progress_percentage.between' => 'نسبة التقدم يجب أن تكون بين 0 و100. | Progress percentage must be between 0 and 100.',

            'github_url.url' => 'رابط GitHub غير صالح. | The GitHub URL is invalid.',

            'github_url.max' => 'رابط GitHub يجب ألا يتجاوز 2048 حرفاً. | The GitHub URL must not exceed 2048 characters.',

            'demo_url.url' => 'رابط العرض التجريبي غير صالح. | The demo URL is invalid.',

            'demo_url.max' => 'رابط العرض التجريبي يجب ألا يتجاوز 2048 حرفاً. | The demo URL must not exceed 2048 characters.',

            'attachments.required' => 'يجب إرفاق ملف واحد على الأقل لتوثيق التقدم. | At least one progress evidence file is required.',

            'attachments.array' => 'ملفات التوثيق يجب أن تكون ضمن قائمة. | Attachments must be an array.',

            'attachments.min' => 'يجب إرفاق ملف توثيق واحد على الأقل. | At least one attachment is required.',

            'attachments.max' => 'لا يمكن إرفاق أكثر من 5 ملفات. | No more than 5 attachments may be uploaded.',

            'attachments.*.required' => 'ملف التوثيق مطلوب. | The attachment file is required.',

            'attachments.*.file' => 'كل عنصر ضمن ملفات التوثيق يجب أن يكون ملفاً صالحاً. | Each attachment must be a valid file.',

            'attachments.*.mimes' => 'صيغة ملف التوثيق غير مدعومة. | The attachment file type is not supported.',

            'attachments.*.max' => 'حجم كل ملف يجب ألا يتجاوز 10 ميغابايت. | Each attachment must not exceed 10 MB.',
        ];

    }
}
