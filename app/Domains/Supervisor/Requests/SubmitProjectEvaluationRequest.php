<?php

namespace App\Domains\Supervisor\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitProjectEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'general_comment' => [
                'nullable',
                'string',
                'max:3000',
            ],

            'items' => [
                'required',
                'array',
                'min:1',
            ],

            'items.*.evaluation_criteria_id' => [
                'required',
                'exists:evaluation_criteria,id',
            ],

            'items.*.score' => [
                'required',
                'numeric',
                'min:0',
            ],

            'items.*.comment' => [
                'nullable',
                'string',
                'max:1500',
            ],

            'items.*.evidence' => [
                'nullable',
                'string',
                'max:3000',
            ],

            'items.*.evidence_urls' => [
                'nullable',
                'array',
            ],

            'items.*.evidence_urls.*' => [
                'url',
            ],
        ];
    }
}
