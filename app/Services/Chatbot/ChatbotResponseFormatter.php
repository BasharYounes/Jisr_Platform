<?php

namespace App\Services\Chatbot;

use App\Services\AI\AIClientInterface;
use Illuminate\Support\Facades\Log;
use JsonException;
use Throwable;

class ChatbotResponseFormatter
{
    public function __construct(
        private readonly AIClientInterface $aiClient,
        private readonly ChatbotResponseFactGuard $factGuard,
    ) {}

    /**
     * @param  array<string, mixed>  $facts
     * @param  array{
     *     required_tokens?: array<int, string>,
     *     ordered_tokens?: array<int, string>,
     *     allowed_percentages?: array<int, string|int|float>
     * }  $guard
     */
    public function format(
        string $mode,
        string $language,
        string $templateContent,
        array $facts,
        array $guard = [],
    ): string {
        $templateContent = trim($templateContent);

        if (! $this->shouldFormat($mode, $templateContent, $facts)) {
            return $templateContent;
        }

        try {
            $result = $this->aiClient->generateJson(
                systemPrompt: $this->systemPrompt(),
                userPrompt: $this->userPrompt(
                    mode: $mode,
                    language: $language,
                    templateContent: $templateContent,
                    facts: $facts,
                ),
                taskType: (string) config(
                    'chatbot.response_formatter.task_type',
                    'default',
                ),
            );
        } catch (Throwable $exception) {
            Log::warning('Chatbot AI response formatting failed; template fallback used.', [
                'mode' => $mode,
                'exception' => $exception::class,
                'code' => $exception->getCode(),
            ]);

            return $templateContent;
        }

        $content = $result['content'] ?? null;

        if (! is_string($content)) {
            return $templateContent;
        }

        $content = trim($content);

        if (! $this->factGuard->passes($content, $guard)) {
            Log::warning('Chatbot AI response rejected by the fact guard; template fallback used.', [
                'mode' => $mode,
            ]);

            return $templateContent;
        }

        return $content;
    }

    /**
     * @param  array<string, mixed>  $facts
     */
    private function shouldFormat(
        string $mode,
        string $templateContent,
        array $facts,
    ): bool {
        if (! config('chatbot.response_formatter.enabled', false)) {
            return false;
        }

        $allowedModes = config('chatbot.response_formatter.modes', []);

        return $templateContent !== ''
            && $facts !== []
            && is_array($allowedModes)
            && in_array($mode, $allowedModes, true);
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You are the response-formatting layer for the Jisr Platform student chatbot.

Your task is ONLY to rewrite the supplied template answer so it sounds natural, clear, concise, and supportive in the requested language.

Strict rules:
1. Use only the supplied facts and the template answer.
2. Do not calculate, infer, recommend, rank, or add any new fact.
3. Preserve every skill name, career-path name, opportunity title, level, percentage, and result order exactly.
4. Do not remove a factual item from the template.
5. Do not mention student names, IDs, emails, or any personal data.
6. Do not create UI actions, links, IDs, or application instructions.
7. Do not use HTML, markdown code fences, or tables.
8. Keep the answer in the requested language.
9. Return valid JSON only in this exact shape:
{"content":"formatted answer"}
PROMPT;
    }

    /**
     * @param  array<string, mixed>  $facts
     *
     * @throws JsonException
     */
    private function userPrompt(
        string $mode,
        string $language,
        string $templateContent,
        array $facts,
    ): string {
        return json_encode([
            'mode' => $mode,
            'language' => $language === 'en' ? 'en' : 'ar',
            'template_answer' => $templateContent,
            'facts' => $facts,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
