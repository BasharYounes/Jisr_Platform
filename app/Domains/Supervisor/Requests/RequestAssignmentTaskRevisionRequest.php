<?php

namespace App\Domains\Supervisor\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RequestAssignmentTaskRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function prepareForValidation(): void
    {
        $raw = $this->resolveRawPayload();

        $payload = $this->normalizePayload($raw);

        if (isset($payload['data']) && is_array($payload['data'])) {
            $payload = $this->normalizePayload($payload['data']);
        }

        $this->merge([
            'feedback' => data_get($payload, 'feedback'),
        ]);
    }

    private function normalizePayload(array $payload): array
    {

        // If the payload is an associative array, return as-is.
        if (! array_is_list($payload)) {
            // Some clients send a single form field that contains a JSON string.
            // Try to decode any JSON string values at the top level.
            foreach ($payload as $key => $value) {
                if (is_string($value) && ($value[0] === '{' || $value[0] === '[')) {
                    $decoded = json_decode($value, true);

                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        return $decoded;
                    }
                }
            }

            return $payload;
        }

        $normalized = [];

        foreach ($payload as $item) {
            if (! is_array($item) || ! array_key_exists('key', $item)) {
                continue;
            }

            if (array_key_exists('enabled', $item) && ! $item['enabled']) {
                continue;
            }

            $normalized[$item['key']] = $item['value'] ?? null;
        }

        return $normalized;
    }

    private function resolveRawPayload(): array
    {
        // Try to decode raw request body first (handles Postman raw JSON)
        $content = $this->getContent();

        if (is_string($content) && trim($content) !== '') {
            $decoded = json_decode($content, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        // Fall back to parsed input
        return $this->all();
    }

    public function rules(): array
    {
        return [
            'feedback' => [
                'required',
                'string',
                'min:10',
                'max:3000',
            ],
        ];
    }
}
