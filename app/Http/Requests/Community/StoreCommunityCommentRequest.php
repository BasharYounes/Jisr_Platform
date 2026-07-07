<?php

namespace App\Http\Requests\Community;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommunityCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'min:2', 'max:2000'],
            'parent_comment_id' => ['nullable', 'integer', 'exists:comments,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'content.required' => 'محتوى التعليق مطلوب.',
            'content.string' => 'محتوى التعليق يجب أن يكون نصاً.',
            'content.min' => 'يجب ألا يقل التعليق عن حرفين.',
            'content.max' => 'يجب ألا يتجاوز التعليق 2000 حرف.',
            'parent_comment_id.exists' => 'التعليق الأب غير موجود.',
        ];
    }
}
