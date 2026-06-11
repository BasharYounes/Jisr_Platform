<?php

namespace App\Http\Requests\CompanyTasks;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCompanyTaskSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
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

            'zip_file' => [
                'nullable',
                'file',
                'mimes:zip',
                'max:51200',
            ],

            'notes' => [
                'required',
                'string',
                'max:5000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'github_url.url' => 'رابط GitHub غير صالح. | The GitHub URL is invalid.',

            'github_url.max' => 'رابط GitHub يجب ألا يتجاوز 2048 حرفاً. | The GitHub URL must not exceed 2048 characters.',

            'demo_url.url' => 'رابط العرض التجريبي غير صالح. | The demo URL is invalid.',

            'demo_url.max' => 'رابط العرض التجريبي يجب ألا يتجاوز 2048 حرفاً. | The demo URL must not exceed 2048 characters.',

            'zip_file.file' => 'ملف المشروع المرفوع غير صالح. | The uploaded project file is invalid.',

            'zip_file.mimes' => 'ملف المشروع يجب أن يكون بصيغة ZIP. | The project file must be a ZIP file.',

            'zip_file.max' => 'حجم ملف المشروع يجب ألا يتجاوز 50 ميغابايت. | The project file must not exceed 50 MB.',

            'notes.required' => 'ملاحظات التسليم مطلوبة. | Submission notes are required.',

            'notes.string' => 'ملاحظات التسليم يجب أن تكون نصاً. | Submission notes must be a string.',

            'notes.max' => 'ملاحظات التسليم يجب ألا تتجاوز 5000 حرف. | Submission notes must not exceed 5000 characters.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (
                    ! $this->filled('github_url')
                    && ! $this->hasFile('zip_file')
                ) {
                    $validator->errors()->add(
                        'submission',
                        'يجب إرفاق رابط GitHub أو ملف ZIP واحد على الأقل. | A GitHub URL or ZIP file is required.'
                    );
                }
            },
        ];
    }
}
