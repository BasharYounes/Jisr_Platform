<?php

namespace App\Services\Chatbot;

use App\Models\ChatbotKnowledgeEntry;
use App\Services\AI\AIClientInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class ChatbotKnowledgeFallbackClassifier
{
    public function __construct(
        private readonly AIClientInterface $aiClient,
    ) {}

    public function classify(
        string $message,
        string $language,
    ): ?ChatbotKnowledgeEntry {
        if (! config('chatbot.knowledge_matching.ai_fallback_enabled', true)) {
            return null;
        }

        $entries = $this->platformHelpEntries();

        if ($entries->isEmpty()) {
            return null;
        }

        try {
            $result = $this->aiClient->generateJson(
                systemPrompt: $this->systemPrompt($entries),
                userPrompt: $this->userPrompt(
                    message: $message,
                    language: $language,
                    entries: $entries,
                ),
                taskType: 'default',
            );
        } catch (Throwable $exception) {
            // Do not fail the conversation if the external provider is unavailable.
            // The platform-help service will return the safe fixed no-match response.
            Log::warning('Chatbot knowledge AI fallback failed.', [
                'exception' => $exception::class,
                'code' => $exception->getCode(),
            ]);

            return null;
        }

        $knowledgeKey = $result['knowledge_key'] ?? null;

        if (! is_string($knowledgeKey)) {
            return null;
        }

        $knowledgeKey = trim($knowledgeKey);

        if ($knowledgeKey === '' || $knowledgeKey === 'out_of_scope') {
            return null;
        }

        // Never trust an arbitrary key returned by the model.
        // It must match one of the active platform-help records loaded above.
        return $entries->firstWhere('key', $knowledgeKey);
    }

    private function platformHelpEntries(): Collection
    {
        return ChatbotKnowledgeEntry::query()
            ->where('category', 'platform_help')
            ->where('is_active', true)
            ->orderBy('id')
            ->get();
    }

    private function systemPrompt(Collection $entries): string
    {
        $allowedKeys = $entries
            ->pluck('key')
            ->push('out_of_scope')
            ->implode(', ');

        return <<<PROMPT
You are a strict intent classifier for the Jisr Platform student assistant.

Your only task is to select exactly one knowledge_key for the user's question.
Do not answer the question and do not explain your choice.
Return valid JSON only in this exact shape:
{"knowledge_key":"one_allowed_key"}

Allowed keys:
{$allowedKeys}

Rules:
1. Select only one key from the allowed list.
2. Use out_of_scope when the question does not match any listed Jisr platform-help topic.
3. Do not invent keys.
4. Do not include markdown, notes, confidence, or any additional fields.
PROMPT;
    }

    private function userPrompt(
        string $message,
        string $language,
        Collection $entries,
    ): string {
        $language = $language === 'en' ? 'en' : 'ar';

        $topics = $entries
            ->map(static function (ChatbotKnowledgeEntry $entry) use ($language): array {
                return [
                    'knowledge_key' => $entry->key,
                    'topic' => $language === 'ar'
                        ? $entry->question_ar
                        : $entry->question_en,
                ];
            })
            ->values()
            ->all();

        return json_encode([
            'language' => $language,
            'user_question' => trim($message),
            'available_topics' => $topics,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
