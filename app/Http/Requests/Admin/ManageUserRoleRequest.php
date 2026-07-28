<?php

namespace App\Http\Requests\Admin;

use App\Domains\Admin\Enums\ManagedUserRole;
use App\Domains\Admin\Enums\UserRoleOperation;
use App\Enums\SupervisorSpecialization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManageUserRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'operation' => [
                'required',
                Rule::enum(UserRoleOperation::class),
            ],

            'role' => [
                'required',
                Rule::enum(ManagedUserRole::class),
            ],

            'role_data' => [
                'nullable',
                'array',
            ],

            'role_data.specialization' => [
                'nullable',
                Rule::enum(SupervisorSpecialization::class),
            ],

            'role_data.is_volunteer' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'operation.required' => 'The role operation is required.',
            'role.required' => 'The role is required.',
            'role_data.specialization.enum' => 'The selected supervisor specialization is invalid.',
            'role_data.is_volunteer.boolean' => 'The is_volunteer field must be true or false.',
        ];
    }
}
