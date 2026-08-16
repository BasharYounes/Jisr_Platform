<?php

namespace App\Http\Requests\Chatbot;

use App\Models\ChatbotConversation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateChatbotConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mode' => [
                'required',
                'string',
                Rule::in(ChatbotConversation::allowedModes()),
            ],
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
