<?php

namespace App\Domains\Supervisor\Requests;

use App\Domains\Supervisor\Enums\ProjectEvaluationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListLeadProjectEvaluationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scope' => [
                'nullable',
                'string',
                Rule::in(['specialization']),
            ],
            'status' => [
                'nullable',
                Rule::enum(ProjectEvaluationStatus::class),
            ],
            'page' => [
                'nullable',
                'integer',
                'min:1',
            ],
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ];
    }
}
