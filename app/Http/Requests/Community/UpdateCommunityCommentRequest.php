<?php

namespace App\Http\Requests\Community;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCommunityCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'min:2', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'content.required' => 'محتوى التعليق مطلوب.',
            'content.string' => 'محتوى التعليق يجب أن يكون نصاً.',
            'content.min' => 'يجب ألا يقل التعليق عن حرفين.',
            'content.max' => 'يجب ألا يتجاوز التعليق 2000 حرف.',
        ];
    }
}
