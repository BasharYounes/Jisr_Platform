<?php

namespace App\Domains\Supervisor\Requests;

use App\Domains\Supervisor\Enums\ProjectEvaluationAppealDecision;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewProjectEvaluationAppealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'decision' => [
                'required',
                Rule::enum(
                    ProjectEvaluationAppealDecision::class
                ),
            ],

            'review_notes' => [
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
            'decision.required' =>
                'The appeal review decision is required.',

            'review_notes.required' =>
                'The review notes are required.',

            'review_notes.min' =>
                'The review notes must contain at least 10 characters.',

            'review_notes.max' =>
                'The review notes may not exceed 3000 characters.',
        ];
    }
}
