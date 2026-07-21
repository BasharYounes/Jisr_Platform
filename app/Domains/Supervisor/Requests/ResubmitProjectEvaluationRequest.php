<?php

namespace App\Domains\Supervisor\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResubmitProjectEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'resolution_note' => [
                'nullable',
                'string',
                'max:3000',
            ],
        ];
    }
}
