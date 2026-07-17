<?php

namespace App\Http\Requests\Community;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCommunityPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'content' => ['sometimes', 'required', 'string', 'min:10', 'max:2000'],
            'type' => [
                'sometimes',
                'required',
                'string',
                Rule::in(['question', 'discussion', 'help', 'tip']),
            ],
        ];
    }
}
