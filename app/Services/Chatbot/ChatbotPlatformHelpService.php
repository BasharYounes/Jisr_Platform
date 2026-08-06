<?php

namespace App\Services\Chatbot;

use App\Models\ChatbotConversation;
use App\Models\ChatbotMessage;
use Illuminate\Support\Facades\DB;

class ChatbotPlatformHelpService
{
    public function __construct(
        private readonly ChatbotKnowledgeMatcher $knowledgeMatcher,
        private readonly ChatbotKnowledgeFallbackClassifier $fallbackClassifier,
    ) {}

    public function answer(
        ChatbotConversation $conversation,
        ChatbotMessage $userMessage,
    ): ChatbotMessage {
        $language = $userMessage->language === 'en' ? 'en' : 'ar';

        // Fast and free path: try deterministic local matching first.
        $entry = $this->knowledgeMatcher->match(
            message: $userMessage->content,
            language: $language,
        );

        // Gemini is used only when local matching cannot make a safe decision.
        // It classifies the question into one of the existing knowledge keys;
        // it never writes the final platform answer.
        if ($entry === null) {
            $entry = $this->fallbackClassifier->classify(
                message: $userMessage->content,
                language: $language,
            );
        }

        $content = $entry !== null
            ? ($language === 'ar' ? $entry->answer_ar : $entry->answer_en)
            : $this->noMatchMessage($language);

        $actions = $entry?->action;

        return DB::transaction(function () use (
            $conversation,
            $userMessage,
            $language,
            $content,
            $actions,
        ): ChatbotMessage {
            $userMessage->update([
                'status' => ChatbotMessage::STATUS_COMPLETED,
                'error_code' => null,
            ]);

            $assistantMessage = $conversation->messages()->create([
                'client_message_id' => null,
                'role' => ChatbotMessage::ROLE_ASSISTANT,
                'content' => $content,
                'language' => $language,
                'status' => ChatbotMessage::STATUS_COMPLETED,
                'actions' => $actions,
                'error_code' => null,
            ]);

            $conversation->update([
                'last_message_at' => $assistantMessage->created_at,
            ]);

            return $assistantMessage;
        });
    }

    private function noMatchMessage(string $language): string
    {
        if ($language === 'en') {
            return 'I could not match your question with the available Jisr Platform information. Please rephrase it as a question about using the platform.';
        }

        return 'لم أتمكن من مطابقة سؤالك مع معلومات منصة جسر المتاحة. أعد صياغته كسؤال واضح عن طريقة استخدام المنصة.';
    }
}
