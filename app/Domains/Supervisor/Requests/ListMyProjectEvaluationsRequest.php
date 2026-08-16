<?php

namespace App\Domains\Supervisor\Requests;

use App\Domains\Supervisor\Enums\ProjectEvaluationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListMyProjectEvaluationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
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
