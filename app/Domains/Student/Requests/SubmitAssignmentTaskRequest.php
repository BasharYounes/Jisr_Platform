<?php

namespace App\Domains\Student\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitAssignmentTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function prepareForValidation(): void
    {
        $payload = $this->normalizePayload($this->all());

        if (isset($payload['data']) && is_array($payload['data'])) {
            $payload = $this->normalizePayload($payload['data']);
        }

        $submissionUrl = data_get($payload, 'submission_url');

        if (! filled($submissionUrl)) {
            $submissionUrl = data_get($payload, 'submissionUrl')
                ?? data_get($payload, 'submission');
        }

        $githubBranchOrLink = data_get($payload, 'github_branch_or_link');

        if (! filled($githubBranchOrLink)) {
            $githubBranchOrLink = data_get($payload, 'githubBranchOrLink')
                ?? data_get($payload, 'github_branch')
                ?? data_get($payload, 'github_link');
        }

        $this->merge([
            'submission_url' => $submissionUrl,
            'github_branch_or_link' => $githubBranchOrLink,
        ]);
    }

    private function normalizePayload(array $payload): array
    {
        if (! array_is_list($payload)) {
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

    public function rules(): array
    {
        return [
            'submission_url' => [
                'nullable',
                'url',
                'max:2048',
            ],

            'github_branch_or_link' => [
                'nullable',
                'string',
                'max:2048',
            ],
        ];
    }

    // public function withValidator($validator): void
    // {
    //     $validator->after(function ($validator) {
    //         if (
    //             ! $this->filled('submission_url')
    //             && ! $this->filled('github_branch_or_link')
    //         ) {
    //             $validator->errors()->add(
    //                 'submission',
    //                 'You must provide either a submission URL or a GitHub branch/link.'
    //             );
    //         }
    //     });
    // }
}
