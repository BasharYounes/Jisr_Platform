<?php

namespace App\Http\Requests\Admin;

use App\Enums\SupervisorSpecialization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminCreateSupervisorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email',
            ],
            'password' => [
                'required',
                'string',
                'min:6',
            ],
            'specialization' => [
                'required',
                Rule::enum(SupervisorSpecialization::class),
            ],
            'is_volunteer' => [
                'sometimes',
                'boolean',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم المشرف مطلوب. | Supervisor name is required.',
            'name.string' => 'اسم المشرف يجب أن يكون نصاً. | Supervisor name must be a string.',
            'name.max' => 'اسم المشرف يجب ألا يتجاوز 255 حرفاً. | Supervisor name must not exceed 255 characters.',

            'email.required' => 'البريد الإلكتروني مطلوب. | Email is required.',
            'email.email' => 'صيغة البريد الإلكتروني غير صالحة. | Email format is invalid.',
            'email.max' => 'البريد الإلكتروني يجب ألا يتجاوز 255 حرفاً. | Email must not exceed 255 characters.',
            'email.unique' => 'البريد الإلكتروني مستخدم مسبقاً. | Email is already in use.',

            'password.required' => 'كلمة المرور مطلوبة. | Password is required.',
            'password.string' => 'كلمة المرور يجب أن تكون نصاً. | Password must be a string.',
            'password.min' => 'كلمة المرور يجب ألا تقل عن 6 محارف. | Password must be at least 6 characters.',

            'specialization.required' => 'اختصاص المشرف مطلوب. | Supervisor specialization is required.',
            'specialization.enum' => 'اختصاص المشرف غير صالح. | Supervisor specialization is invalid.',

            'is_volunteer.boolean' => 'قيمة التطوع يجب أن تكون true أو false. | is_volunteer must be true or false.',
        ];
    }
}
