<?php

namespace App\Services\MarketAnalysis;

use App\Models\MarketJobPosting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MarketJobPostingImportService
{
    public function __construct(
        private readonly MarketSkillExtractionService $skillExtractionService
    ) {}

    /**
     * Import or update one market job posting, then extract its skills.
     */
    public function import(array $data): MarketJobPosting
    {
        $title = trim((string) ($data['title'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));

        if ($title === '') {
            throw new \InvalidArgumentException('Job posting title is required.');
        }

        if ($description === '') {
            throw new \InvalidArgumentException('Job posting description is required.');
        }

        $sourceType = $data['source_type'] ?? 'dataset';
        $sourceName = $data['source_name'] ?? null;
        $externalId = $data['external_id'] ?? null;

        $contentHash = $this->makeContentHash(
            $sourceName,
            $externalId,
            $title,
            $description
        );

        return DB::transaction(function () use (
            $data,
            $title,
            $description,
            $sourceType,
            $sourceName,
            $externalId,
            $contentHash
        ): MarketJobPosting {
            $posting = MarketJobPosting::query()->updateOrCreate(
                ['content_hash' => $contentHash],
                [
                    'source_type' => $sourceType,
                    'source_name' => $sourceName,
                    'external_id' => $externalId,
                    'url' => $data['url'] ?? null,
                    'title' => $title,
                    'description' => $description,
                    'company_name' => $data['company_name'] ?? null,
                    'location' => $data['location'] ?? null,
                    'language' => $data['language'] ?? $this->detectLanguage($title . ' ' . $description),
                    'career_path_id' => $data['career_path_id'] ?? null,
                    'published_at' => $this->parseDate($data['published_at'] ?? null),
                    'imported_at' => now(),
                    'status' => $data['status'] ?? 'active',
                ]
            );

            $this->skillExtractionService->extractForJobPosting($posting);

            return $posting->load([
                'careerPath',
                'skillOccurrences.skill',
                'skillOccurrences.skillAlias',
            ]);
        });
    }

    private function makeContentHash(
        ?string $sourceName,
        ?string $externalId,
        string $title,
        string $description
    ): string {
        /*
         * If the source has a stable external id, use it with source_name.
         * Otherwise, hash the actual job content.
         */
        if ($sourceName && $externalId) {
            return hash('sha256', $sourceName . '|' . $externalId);
        }

        return hash('sha256', Str::lower($title) . '|' . Str::lower($description));
    }

    private function detectLanguage(string $text): string
    {
        if (preg_match('/\p{Arabic}/u', $text) === 1) {
            if (preg_match('/[a-zA-Z]/', $text) === 1) {
                return 'mixed';
            }

            return 'ar';
        }

        if (preg_match('/[a-zA-Z]/', $text) === 1) {
            return 'en';
        }

        return 'unknown';
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
