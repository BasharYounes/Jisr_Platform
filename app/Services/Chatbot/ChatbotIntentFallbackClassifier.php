<?php

namespace App\Services\Chatbot;

use App\Services\AI\AIClientInterface;
use Illuminate\Support\Facades\Log;
use JsonException;
use Throwable;

class ChatbotIntentFallbackClassifier
{
    public function __construct(
        private readonly AIClientInterface $aiClient,
    ) {}

    /**
     * @param  array<string, string>  $availableIntents
     */
    public function classify(
        string $mode,
        string $message,
        string $language,
        array $availableIntents,
    ): ?string {
        if (! config('chatbot.intent_classification.ai_fallback_enabled', true)) {
            return null;
        }

        if ($availableIntents === []) {
            return null;
        }

        try {
            $result = $this->aiClient->generateJson(
                systemPrompt: $this->systemPrompt($mode, $availableIntents),
                userPrompt: $this->userPrompt(
                    mode: $mode,
                    message: $message,
                    language: $language,
                    availableIntents: $availableIntents,
                ),
                taskType: (string) config(
                    'chatbot.intent_classification.task_type',
                    'default',
                ),
            );
        } catch (Throwable $exception) {
            Log::warning('Chatbot intent AI fallback failed.', [
                'mode' => $mode,
                'exception' => $exception::class,
                'code' => $exception->getCode(),
            ]);

            return null;
        }

        $intent = $result['intent'] ?? null;

        if (! is_string($intent)) {
            return null;
        }

        $intent = trim($intent);

        // Never trust an arbitrary intent returned by the model.
        return array_key_exists($intent, $availableIntents)
            ? $intent
            : null;
    }

    /**
     * @param  array<string, string>  $availableIntents
     */
    private function systemPrompt(string $mode, array $availableIntents): string
    {
        $allowed = implode(', ', array_keys($availableIntents));

        return <<<PROMPT
You are a strict intent-classification layer for the Jisr Platform student chatbot.

Conversation mode: {$mode}

Your only task is to select exactly one intent from the allowed list.
Do not answer the student, do not calculate anything, and do not explain your choice.
Return valid JSON only in this exact shape:
{"intent":"one_allowed_intent"}

Allowed intents:
{$allowed}

Rules:
1. Select only one exact intent from the allowed list.
2. Use out_of_scope when the question does not belong to this conversation mode.
3. Use summary only when the student asks for a general overview within this mode.
4. Do not invent intents or include confidence, notes, markdown, or extra fields.
PROMPT;
    }

    /**
     * @param  array<string, string>  $availableIntents
     *
     * @throws JsonException
     */
    private function userPrompt(
        string $mode,
        string $message,
        string $language,
        array $availableIntents,
    ): string {
        return json_encode([
            'mode' => $mode,
            'language' => $language === 'en' ? 'en' : 'ar',
            'user_question' => trim($message),
            'available_intents' => collect($availableIntents)
                ->map(fn (string $description, string $intent): array => [
                    'intent' => $intent,
                    'description' => $description,
                ])
                ->values()
                ->all(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
