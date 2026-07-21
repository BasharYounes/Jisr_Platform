<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use App\Enums\SupervisorSpecialization;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
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
            'role' => 'required|in:student,company,supervisor',

            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',

            //  student fields
            // 'university' => 'required_if:role,student|string',
            // 'major' => 'nullable:role,student|string',
            // 'graduation_year' => 'nullable:role,student|integer|digits:4',
            // 'phone' => 'required_if:role,student|regex:/^09\d{8}$/',
            // 'bio' => 'nullable|string|max:255',
            // 'profile_picture' => ['nullable', 'image', 'max:2048'],

            //  company fields
            'industry' => 'required_if:role,company|string',
            'website' => 'nullable|url',
            'documentation_file' => 'required_if:role,company|file|max:2048|mimes:pdf,jpg,jpeg,png,doc,docx',
            'location' => 'required_if:role,company|string',
            // 'description' => 'nullable|string|max:1000',

            // supervisor fields
            'specialization' => [
                'required_if:role,supervisor',
                Rule::enum(SupervisorSpecialization::class),
            ],
            'is_volunteer' => 'nullable|boolean',

        ];
    }
}
