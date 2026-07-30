<?php

namespace App\Services\MarketAnalysis\Adapters;

use App\Interfaces\JobSourceAdapterInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class ArbeitnowJobSourceAdapter implements JobSourceAdapterInterface
{
    public function sourceName(): string
    {
        return 'arbeitnow';
    }

    public function fetchPage(int $page = 1): array
    {
        $query = [
            'page' => max(1, $page),
        ];

        $response = Http::acceptJson()
            ->connectTimeout(
                (int) config(
                    'services.arbeitnow.connect_timeout',
                    5
                )
            )
            ->timeout(
                (int) config(
                    'services.arbeitnow.timeout',
                    15
                )
            )
            ->retry(2, 500)
            ->get(
                (string) config('services.arbeitnow.base_url'),
                $query
            )
            ->throw();

        $payload = $response->json();

        if (
            ! is_array($payload)
            || ! isset($payload['data'])
            || ! is_array($payload['data'])
        ) {
            throw new RuntimeException(
                'Invalid response received from Arbeitnow API.'
            );
        }

        $jobs = [];

        foreach ($payload['data'] as $item) {
            if (! is_array($item)) {
                continue;
            }

            $normalizedJob = $this->normalizeJob($item);

            if ($normalizedJob !== null) {
                $jobs[] = $normalizedJob;
            }
        }

        return [
            'jobs' => $jobs,

            'current_page' => (int) data_get(
                $payload,
                'meta.current_page',
                $page
            ),

            'has_more' => filled(
                data_get($payload, 'links.next')
            ),
        ];
    }

    /**
     * تحويل إعلان Arbeitnow إلى الشكل الذي تفهمه
     * MarketJobPostingImportService.
     */
    private function normalizeJob(array $item): ?array
    {
        $title = trim((string) ($item['title'] ?? ''));

        $description = $this->cleanDescription(
            (string) ($item['description'] ?? '')
        );

        if ($title === '' || $description === '') {
            return null;
        }

        return [
            'source_type' => 'api',
            'source_name' => $this->sourceName(),

            /*
             * slug هو المعرّف الثابت المتاح من Arbeitnow.
             * سنستخدمه لمنع تكرار الإعلان.
             */
            'external_id' => isset($item['slug'])
                ? (string) $item['slug']
                : null,

            'url' => isset($item['url'])
                ? (string) $item['url']
                : null,

            'title' => $title,
            'description' => $description,

            'company_name' => isset($item['company_name'])
                ? trim((string) $item['company_name'])
                : null,

            'location' => isset($item['location'])
                ? trim((string) $item['location'])
                : null,

            /*
             * نترك تحديد اللغة للخدمة الموجودة مسبقاً.
             */
            'language' => null,

            /*
             * created_at في Arbeitnow عبارة عن Unix timestamp.
             */
            'published_at' => $this->normalizePublishedAt(
                $item['created_at'] ?? null
            ),

            'status' => 'active',
        ];
    }

    private function cleanDescription(string $description): string
    {
        /*
         * أوصاف Arbeitnow تحتوي HTML.
         * نحوله إلى نص عادي قبل استخراج المهارات.
         */
        $text = html_entity_decode(
            strip_tags($description),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        $normalized = preg_replace('/\s+/u', ' ', $text);

        return trim($normalized ?? $text);
    }

    private function normalizePublishedAt(
        mixed $createdAt
    ): ?string {
        if (! is_numeric($createdAt)) {
            return null;
        }

        return CarbonImmutable::createFromTimestampUTC(
            (int) $createdAt
        )->toIso8601String();
    }
}
