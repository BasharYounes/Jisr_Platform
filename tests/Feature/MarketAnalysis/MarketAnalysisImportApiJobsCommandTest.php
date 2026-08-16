<?php

namespace Tests\Feature\MarketAnalysis;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use App\Services\AI\AIClientInterface;

class MarketAnalysisImportApiJobsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_api_jobs_without_creating_duplicates(): void
    {
        config()->set(
            'services.arbeitnow.base_url',
            'https://arbeitnow.test/api/job-board-api'
        );

        $supportedPaths = [
            'Backend Developer',
            'Frontend Developer',
            'Mobile Developer',
            'DevOps Engineer',
        ];

        foreach ($supportedPaths as $pathName) {
            DB::table('career_paths')->updateOrInsert(
                ['Name' => $pathName],
                [
                    'Description' =>
                        'Temporary market import classifier test path.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $backendPathId = (int) DB::table('career_paths')
            ->where('Name', 'Backend Developer')
            ->value('CareerPathID');

        Http::fake([
            'https://arbeitnow.test/api/job-board-api*' => Http::response([
                'data' => [
                    [
                        'slug' => 'backend-engineer-test-123',
                        'company_name' => 'Test Company',
                        'title' => 'Backend Engineer',
                        'description' => '<p>PHP Laravel REST API</p>',
                        'url' => 'https://arbeitnow.test/jobs/backend-engineer-test-123',
                        'location' => 'Berlin',
                        'created_at' => 1710000000,
                    ],
                ],
                'links' => [
                    'next' => null,
                ],
                'meta' => [
                    'current_page' => 1,
                ],
            ], 200),
        ]);

        $this->app->instance(
            AIClientInterface::class,
            new class implements AIClientInterface
            {
                public function generateJson(
                    string $systemPrompt,
                    string $userPrompt,
                    string $taskType = 'default'
                ): array {
                    return [
                        'detected_path' =>
                            'Backend Developer',

                        'reason' =>
                            'The primary responsibility is backend API development.',

                        'evidence' => [
                            'Develops PHP Laravel services',
                            'Builds REST APIs',
                        ],
                    ];
                }
            }
        );

        $this->artisan('market:import-api-jobs', [
            '--pages' => 1,
            '--limit' => 1,
        ])->assertSuccessful();

        $this->assertDatabaseHas('market_job_postings', [
            'source_name' => 'arbeitnow',
            'external_id' => 'backend-engineer-test-123',
            'title' => 'Backend Engineer',
            'career_path_id' => $backendPathId,
            'classification_status' => 'classified',
            'classification_method' => 'gemini_path_v1',
        ]);

        $this->assertSame(
            1,
            DB::table('market_job_postings')
                ->where('source_name', 'arbeitnow')
                ->count()
        );

        // تشغيل الأمر مرة ثانية يجب أن يحدث الإعلان نفسه،
        // وليس إنشاء نسخة جديدة.
        $this->artisan('market:import-api-jobs', [
            '--pages' => 1,
            '--limit' => 1,
        ])->assertSuccessful();

        $this->assertSame(
            1,
            DB::table('market_job_postings')
                ->where('source_name', 'arbeitnow')
                ->count()
        );
    }

    public function test_it_falls_back_to_rules_when_gemini_fails(): void
    {
        config()->set(
            'services.arbeitnow.base_url',
            'https://arbeitnow.test/api/job-board-api'
        );

        $this->app->instance(
            AIClientInterface::class,
            new class implements AIClientInterface
            {
                public function generateJson(
                    string $systemPrompt,
                    string $userPrompt,
                    string $taskType = 'default'
                ): array {
                    throw new \RuntimeException(
                        'Simulated Gemini failure.'
                    );
                }
            }
        );

        $supportedPaths = [
            'Backend Developer',
            'Frontend Developer',
            'Mobile Developer',
            'DevOps Engineer',
        ];

        foreach ($supportedPaths as $pathName) {
            DB::table('career_paths')->updateOrInsert(
                ['Name' => $pathName],
                [
                    'Description' =>
                        'Temporary fallback classifier test path.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $backendPathId = (int) DB::table('career_paths')
            ->where('Name', 'Backend Developer')
            ->value('CareerPathID');

        Http::fake([
            'https://arbeitnow.test/api/job-board-api*' =>
                Http::response([
                    'data' => [
                        [
                            'slug' =>
                                'fallback-backend-test-123',

                            'company_name' =>
                                'Fallback Test Company',

                            'title' =>
                                'Backend Engineer',

                            'description' =>
                                '<p>PHP Laravel REST API</p>',

                            'url' =>
                                'https://arbeitnow.test/jobs/fallback-backend-test-123',

                            'location' => 'Berlin',
                            'created_at' => 1710000000,
                        ],
                    ],

                    'links' => [
                        'next' => null,
                    ],

                    'meta' => [
                        'current_page' => 1,
                    ],
                ], 200),
        ]);

        $this->artisan('market:import-api-jobs', [
            '--pages' => 1,
            '--limit' => 1,
        ])->assertSuccessful();

        $this->assertDatabaseHas(
            'market_job_postings',
            [
                'source_name' => 'arbeitnow',

                'external_id' =>
                    'fallback-backend-test-123',

                'title' => 'Backend Engineer',

                'career_path_id' =>
                    $backendPathId,

                'classification_status' =>
                    'classified',

                /*
                * يثبت أن Gemini فشل وأن المصنف القديم
                * هو الذي حفظ النتيجة.
                */
                'classification_method' =>
                    'weighted_rules_v1',
            ]
        );
    }
}
