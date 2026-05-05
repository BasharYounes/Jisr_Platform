<?php

namespace App\Services\AI;

use App\Exceptions\AIProviderException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiClient implements AIClientInterface
{
    public function generateJson(string $systemPrompt, string $userPrompt): array
    {
        $apiKey = config('services.gemini.api_key');
        $model = config('services.gemini.model', 'gemini-2.5-flash-lite');
        $timeout = (int) config('services.gemini.timeout', 60);

        if (blank($apiKey)) {
            throw new RuntimeException('Gemini API key is missing.');
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

        $response = Http::timeout($timeout)
            ->retry(2, 500)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'x-goog-api-key' => $apiKey,
            ])
            ->post($url, [
                'systemInstruction' => [
                    'parts' => [
                        ['text' => $systemPrompt],
                    ],
                ],
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => $userPrompt],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.2,
                    'responseMimeType' => 'application/json',
                ],
            ]);

        if (!$response->successful()) {
            throw new AIProviderException(
                'Gemini request failed: ' . $response->status() . ' - ' . $response->body()
            );
        }

        $text = $response->json('candidates.0.content.parts.0.text');

        if (blank($text)) {
            throw new AIProviderException('Gemini returned empty response.');
        }

        return $this->decodeJson($text);
    }

    private function decodeJson(string $text): array
    {
        $text = trim($text);

        $decoded = json_decode($text, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        preg_match('/\{.*\}/s', $text, $matches);

        if (!empty($matches[0])) {
            $decoded = json_decode($matches[0], true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        throw new AIProviderException('Invalid JSON returned from Gemini: ' . $text);
    }
}
