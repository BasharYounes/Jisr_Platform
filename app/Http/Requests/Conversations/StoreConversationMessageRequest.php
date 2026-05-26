<?php

namespace App\Http\Requests\Conversations;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreConversationMessageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'min:1', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'content.required' => [
                'ar' => 'محتوى الرسالة مطلوب.',
                'en' => 'Message content is required.',
            ],
            'content.string' => [
                'ar' => 'محتوى الرسالة يجب أن يكون نصاً.',
                'en' => 'Message content must be a string.',
            ],
            'content.min' => [
                'ar' => 'محتوى الرسالة لا يمكن أن يكون فارغاً.',
                'en' => 'Message content cannot be empty.',
            ],
            'content.max' => [
                'ar' => 'محتوى الرسالة يجب ألا يتجاوز 2000 حرف.',
                'en' => 'Message content must not exceed 2000 characters.',
            ],
        ];
    }
}
