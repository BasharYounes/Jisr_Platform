<?php

namespace App\Services\Chatbot;

class ChatbotResponseFactGuard
{
    /**
     * @param  array{
     *     required_tokens?: array<int, string>,
     *     ordered_tokens?: array<int, string>,
     *     allowed_percentages?: array<int, string|int|float>
     * }  $guard
     */
    public function passes(string $content, array $guard = []): bool
    {
        $content = trim($content);

        if ($content === '') {
            return false;
        }

        $maxLength = max(
            200,
            (int) config('chatbot.response_formatter.max_output_length', 2500),
        );

        if (mb_strlen($content) > $maxLength) {
            return false;
        }

        if (str_contains($content, '```') || preg_match('/<\/?[a-z][^>]*>/iu', $content) === 1) {
            return false;
        }

        $requiredTokens = collect($guard['required_tokens'] ?? [])
            ->filter(fn (mixed $token): bool => is_scalar($token) && trim((string) $token) !== '')
            ->map(fn (mixed $token): string => trim((string) $token))
            ->unique()
            ->values();

        foreach ($requiredTokens as $token) {
            if (! $this->containsToken($content, $token)) {
                return false;
            }
        }

        $orderedTokens = collect($guard['ordered_tokens'] ?? [])
            ->filter(fn (mixed $token): bool => is_scalar($token) && trim((string) $token) !== '')
            ->map(fn (mixed $token): string => trim((string) $token))
            ->unique()
            ->values();

        $lastPosition = -1;

        foreach ($orderedTokens as $token) {
            $position = mb_stripos($content, $token);

            if ($position === false || $position < $lastPosition) {
                return false;
            }

            $lastPosition = $position;
        }

        return $this->percentagesAreAllowed(
            content: $content,
            allowedPercentages: $guard['allowed_percentages'] ?? [],
        );
    }


    private function containsToken(string $content, string $token): bool
    {
        if (preg_match('/^\d+(?:\.\d+)?%$/', $token) === 1) {
            $number = substr($token, 0, -1);

            return preg_match(
                '/(?<![\d.])'.preg_quote($number, '/').'(?![\d.])\s*%/u',
                $content,
            ) === 1;
        }

        if (preg_match('/^\d+(?:\.\d+)?$/', $token) === 1) {
            return $this->containsNumber($content, $token);
        }

        return mb_stripos($content, $token) !== false;
    }


    private function containsNumber(string $content, string $expected): bool
    {
        preg_match_all(
            '/(?<!\d)\d+(?:\.\d+)?(?!\d)/u',
            $content,
            $matches,
        );

        $expectedNumber = $this->normalizeNumber($expected);

        return collect($matches[0] ?? [])->contains(
            fn (mixed $number): bool => $this->normalizeNumber($number) === $expectedNumber,
        );
    }

    /**
     * @param  array<int, string|int|float>  $allowedPercentages
     */
    private function percentagesAreAllowed(
        string $content,
        array $allowedPercentages,
    ): bool {
        preg_match_all('/(?<![\d.])(\d+(?:\.\d+)?)\s*%/u', $content, $matches);

        $foundPercentages = collect($matches[1] ?? [])
            ->map(fn (mixed $value): string => $this->normalizeNumber($value))
            ->unique()
            ->values();

        if ($foundPercentages->isEmpty()) {
            return true;
        }

        $allowed = collect($allowedPercentages)
            ->map(fn (mixed $value): string => $this->normalizeNumber($value))
            ->unique()
            ->values();

        if ($allowed->isEmpty()) {
            return false;
        }

        return $foundPercentages->every(
            fn (string $percentage): bool => $allowed->contains($percentage),
        );
    }

    private function normalizeNumber(mixed $value): string
    {
        $number = (float) $value;

        return fmod($number, 1.0) === 0.0
            ? (string) (int) $number
            : rtrim(rtrim(number_format($number, 4, '.', ''), '0'), '.');
    }
}
