<?php

namespace App\Domains\Supervisor\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'sometimes',
                'nullable',
                'string',
            ],

            'level' => [
                'sometimes',
                'required',
                'in:Beginner,Intermediate,Advanced',
            ],

            'expected_outcome' => [
                'sometimes',
                'required',
                'string',
            ],

            'max_students' => [
                'sometimes',
                'nullable',
                'integer',
                'min:1',
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->hasAny([
                'title',
                'description',
                'level',
                'expected_outcome',
                'max_students',
            ])) {
                $validator->errors()->add(
                    'template',
                    'At least one project template field must be provided.'
                );
            }
        });
    }
}
