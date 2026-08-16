<?php

namespace App\Http\Requests\Chatbot;

use Illuminate\Foundation\Http\FormRequest;

class SendChatbotMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => [
                'required',
                'string',
                'min:1',
                'max:'.config('chatbot.max_message_length', 2000),
            ],
            'client_message_id' => ['required', 'uuid'],
        ];
    }
}
