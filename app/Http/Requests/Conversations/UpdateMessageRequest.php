<?php

namespace App\Http\Requests\Conversations;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'min:1', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'content.required' =>
                'محتوى الرسالة مطلوب | Message content is required.',

            'content.string' =>
                'محتوى الرسالة يجب أن يكون نصاً | Message content must be a string.',

            'content.min' =>
                'محتوى الرسالة لا يمكن أن يكون فارغاً | Message content cannot be empty.',

            'content.max' =>
                'محتوى الرسالة يجب ألا يتجاوز 5000 حرف | Message content must not exceed 5000 characters.',
        ];
    }
}