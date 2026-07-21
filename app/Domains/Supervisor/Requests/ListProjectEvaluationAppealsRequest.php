<?php

namespace App\Domains\Supervisor\Requests;

use App\Domains\Supervisor\Enums\ProjectEvaluationAppealStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListProjectEvaluationAppealsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'sometimes',
                Rule::enum(
                    ProjectEvaluationAppealStatus::class
                ),
            ],

            'per_page' => [
                'sometimes',
                'integer',
                'min:1',
                'max:100',
            ],
        ];
    }
}
