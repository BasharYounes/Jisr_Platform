<?php

namespace App\Domains\Supervisor\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'project_template_id' => [
                'required',
                'exists:project_templates,id',
            ],

            'students' => [
                'required',
                'array',
                'min:1',
            ],

            'students.*.student_id' => [
                'required',
                'exists:users,id',
                'distinct',
            ],

            'students.*.role' => [
                'nullable',
                'string',
                'max:100',
            ],
        ];
    }
}
