<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StudentProfileRequest extends FormRequest
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
            'email' => ['sometimes', 'email', 'max:255'],
            'profile_picture' => [
                'sometimes',
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],

            // student_profiles table
            'university' => ['sometimes', 'nullable', 'string', 'max:255'],
            'major' => ['sometimes', 'nullable', 'string', 'max:128'],
            'graduation_year' => [
            'sometimes',
            'nullable',
            'integer',
            'digits:4',
            'between:1900,' . now()->year,],

            'phone' => [
                 'sometimes',
                 'nullable',
                 'regex:/^(09\d{8}|\+9639\d{8})$/',
                ],        
            ];

    }
          public function messages(): array
    {
        return [
            // Name
            'name.string' => 'الاسم يجب أن يكون نصًا صالحًا. | The name must be a valid text value.',
            'name.max' => 'الاسم يجب ألا يتجاوز 255 محرفًا. | The name may not be greater than 255 characters.',

            // Bio
            'bio.string' => 'النبذة الشخصية يجب أن تكون نصًا صالحًا. | The bio must be a valid text value.',

            // Email
            'email.email' => 'البريد الإلكتروني غير صالح. | The email address must be valid.',
            'email.max' => 'البريد الإلكتروني يجب ألا يتجاوز 255 محرفًا. | The email address may not be greater than 255 characters.',
            'email.unique' => 'هذا البريد الإلكتروني مستخدم مسبقًا. | This email address is already taken.',

            // Profile Picture
            'profile_picture.image' => 'صورة الملف الشخصي يجب أن تكون ملف صورة صالح. | The profile picture must be a valid image file.',
            'profile_picture.mimes' => 'صيغة صورة الملف الشخصي يجب أن تكون: jpg أو jpeg أو png أو webp. | The profile picture must be a file of type: jpg, jpeg, png, or webp.',
            'profile_picture.max' => 'حجم صورة الملف الشخصي يجب ألا يتجاوز 2 ميغابايت. | The profile picture may not be greater than 2 MB.',

            // University
            'university.string' => 'اسم الجامعة يجب أن يكون نصًا صالحًا. | The university must be a valid text value.',
            'university.max' => 'اسم الجامعة يجب ألا يتجاوز 255 محرفًا. | The university may not be greater than 255 characters.',

            // Major
            'major.string' => 'التخصص يجب أن يكون نصًا صالحًا. | The major must be a valid text value.',
            'major.max' => 'التخصص يجب ألا يتجاوز 128 محرفًا. | The major may not be greater than 128 characters.',

            // Graduation Year
            'graduation_year.integer' => 'سنة التخرج يجب أن تكون رقمًا صحيحًا. | The graduation year must be a valid numeric year.',
            'graduation_year.digits' => 'سنة التخرج يجب أن تتكون من 4 أرقام. | The graduation year must be exactly 4 digits.',
            'graduation_year.between' => 'سنة التخرج يجب أن تكون بين 1900 والسنة الحالية. | The graduation year must be between 1900 and the current year.',

            // Phone
            'phone.regex' => 'رقم الهاتف يجب أن يكون بصيغة 09xxxxxxxx أو +9639xxxxxxxx. | The phone number must be in the format 09xxxxxxxx or +9639xxxxxxxx.',        ];
    }

    /**
     * Custom attribute names.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'student name / اسم الطالب',
            'bio' => 'bio / النبذة الشخصية',
            'email' => 'email address / البريد الإلكتروني',
            'profile_picture' => 'profile picture / صورة الملف الشخصي',
            'university' => 'university / الجامعة',
            'major' => 'major / التخصص',
            'graduation_year' => 'graduation year / سنة التخرج',
            'phone' => 'phone number / رقم الهاتف',
        ];
    
    }
}
