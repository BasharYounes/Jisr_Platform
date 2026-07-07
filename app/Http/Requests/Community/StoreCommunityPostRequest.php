<?php

namespace App\Http\Requests\Community;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommunityPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'min:10', 'max:2000'],
            'type' => [
                'required',
                'string',
                Rule::in(['question', 'discussion', 'help', 'tip']),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'content.required' => 'محتوى المنشور مطلوب.',
            'content.string' => 'محتوى المنشور يجب أن يكون نصاً.',
            'content.min' => 'يجب ألا يقل محتوى المنشور عن 10 أحرف.',
            'content.max' => 'يجب ألا يتجاوز محتوى المنشور 2000 حرف.',
            'type.required' => 'نوع المنشور مطلوب.',
            'type.string' => 'نوع المنشور يجب أن يكون نصاً.',
            'type.in' => 'نوع المنشور غير صالح، يجب أن يكون أحد الأنواع التالية: question, discussion, help, tip.',
        ];
    }
}
