<?php

namespace App\Domains\Supervisor\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateProjectTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
         return [
           'title' => [
                'required',
                'string',
                'max:255'
            ],
           'description' => [
                'nullable',
                 'string'
            ],
           'level' => [
                'required',
                'in:Beginner,Intermediate,Advanced'
            ],
           'expected_outcome' => [
                'required',
                'string'
            ],
        ];
    }
}
