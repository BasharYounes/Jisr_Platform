<?php

namespace Tests\Feature\MarketAnalysis;

use App\Interfaces\JobSourceAdapterInterface;
use App\Services\MarketAnalysis\Adapters\ArbeitnowJobSourceAdapter;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MarketAnalysisArbeitnowJobSourceAdapterTest extends TestCase
{
    public function test_it_resolves_arbeitnow_adapter_from_container(): void
    {
        $adapter = app(JobSourceAdapterInterface::class);

        $this->assertInstanceOf(
            ArbeitnowJobSourceAdapter::class,
            $adapter
        );
    }

    public function test_it_fetches_and_normalizes_arbeitnow_jobs(): void
    {
        config()->set(
            'services.arbeitnow.base_url',
            'https://arbeitnow.test/api/job-board-api'
        );

        Http::fake([
            'https://arbeitnow.test/api/job-board-api*' => Http::response([
                'data' => [
                    [
                        'slug' => 'backend-developer-example-123',
                        'company_name' => 'Example Company',
                        'title' => 'Backend Developer',
                        'description' => '<p>Build APIs with <strong>Laravel</strong> &amp; PHP.</p>',
                        'remote' => true,
                        'url' => 'https://arbeitnow.test/jobs/backend-developer-example-123',
                        'tags' => [
                            'PHP',
                            'Laravel',
                        ],
                        'job_types' => [
                            'full_time',
                        ],
                        'location' => 'Berlin',
                        'created_at' => 1710000000,
                    ],

                    /*
                     * هذا الإعلان غير صالح لعدم وجود عنوان.
                     * يجب أن يتجاهله الـAdapter.
                     */
                    [
                        'slug' => 'invalid-job',
                        'title' => '',
                        'description' => 'Invalid job description.',
                    ],
                ],

                'links' => [
                    'next' => 'https://arbeitnow.test/api/job-board-api?page=3',
                ],

                'meta' => [
                    'current_page' => 2,
                ],
            ], 200),
        ]);

        $adapter = app(JobSourceAdapterInterface::class);

        $result = $adapter->fetchPage(2);

        $this->assertSame(2, $result['current_page']);
        $this->assertTrue($result['has_more']);
        $this->assertCount(1, $result['jobs']);

        $job = $result['jobs'][0];

        $this->assertSame('api', $job['source_type']);
        $this->assertSame('arbeitnow', $job['source_name']);

        $this->assertSame(
            'backend-developer-example-123',
            $job['external_id']
        );

        $this->assertSame(
            'https://arbeitnow.test/jobs/backend-developer-example-123',
            $job['url']
        );

        $this->assertSame(
            'Backend Developer',
            $job['title']
        );

        $this->assertSame(
            'Build APIs with Laravel & PHP.',
            $job['description']
        );

        $this->assertSame(
            'Example Company',
            $job['company_name']
        );

        $this->assertSame(
            'Berlin',
            $job['location']
        );

        $this->assertNull($job['language']);
        $this->assertSame('active', $job['status']);

        $this->assertSame(
            CarbonImmutable::createFromTimestampUTC(1710000000)
                ->toIso8601String(),
            $job['published_at']
        );

        Http::assertSent(function (Request $request): bool {
            parse_str(
                (string) parse_url(
                    $request->url(),
                    PHP_URL_QUERY
                ),
                $query
            );

            return $request->method() === 'GET'
                && str_starts_with(
                    $request->url(),
                    'https://arbeitnow.test/api/job-board-api'
                )
                && ($query['page'] ?? null) === '2';
        });
    }
}
