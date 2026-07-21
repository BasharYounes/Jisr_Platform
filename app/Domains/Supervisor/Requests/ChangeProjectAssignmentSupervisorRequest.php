<?php

namespace App\Domains\Supervisor\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChangeProjectAssignmentSupervisorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'new_supervisor_id' => [
                'bail',
                'required',
                'integer',
                'exists:users,id',
            ],

            'reason' => [
                'bail',
                'required',
                'string',
                'min:10',
                'max:3000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'new_supervisor_id.required' =>
                'The new supervisor is required.',

            'new_supervisor_id.exists' =>
                'The selected supervisor does not exist.',

            'reason.required' =>
                'The supervisor change reason is required.',

            'reason.min' =>
                'The supervisor change reason must contain at least 10 characters.',

            'reason.max' =>
                'The supervisor change reason may not exceed 3000 characters.',
        ];
    }
}
