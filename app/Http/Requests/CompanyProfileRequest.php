<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CompanyProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // users table
            'name' => ['sometimes', 'string', 'max:255'],
            'bio' => ['sometimes', 'nullable', 'string'],
            'profile_picture_url' => ['sometimes', 'nullable', 'image', 'max:2048', 'mimes:jpg,jpeg,png,webp'],
            'email' => ['sometimes', 'string', 'email', 'max:255'],
            // companies table
            'industry' => ['sometimes', 'nullable', 'string', 'max:128'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'website' => ['sometimes', 'nullable', 'url', 'max:255'],

            // file upload
            'documentation_file' => [
                'sometimes',
                'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png,doc,docx',
                'max:5120',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.string' => 'اسم الشركة يجب أن يكون نصاً.',
            'name.max' => 'اسم الشركة يجب ألا يتجاوز 255 حرفاً.',

            'industry.string' => 'مجال الشركة يجب أن يكون نصاً.',
            'industry.max' => 'مجال الشركة يجب ألا يتجاوز 128 حرفاً.',

            'location.string' => 'موقع الشركة يجب أن يكون نصاً.',

            'website.url' => 'رابط الموقع الإلكتروني غير صالح.',
            'website.max' => 'رابط الموقع الإلكتروني يجب ألا يتجاوز 255 حرفاً.',

            'documentation_file.file' => 'ملف التوثيق يجب أن يكون ملفاً صالحاً.',
            'documentation_file.mimes' => 'ملف التوثيق يجب أن يكون بصيغة pdf أو صورة أو doc/docx.',
            'documentation_file.max' => 'حجم ملف التوثيق يجب ألا يتجاوز 5MB.',
        ];
    }
}
