<?php

namespace App\Http\Requests\AI;

use Illuminate\Foundation\Http\FormRequest;

class GenerateAILearningPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'weeks' => ['nullable', 'integer', 'min:1', 'max:8'],
            'hours_per_week' => ['nullable', 'integer', 'min:1', 'max:20'],
        ];
    }
}
