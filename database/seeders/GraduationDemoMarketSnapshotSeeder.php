<?php

namespace Database\Seeders;

use App\Models\AssessmentSession;
use App\Services\MarketAnalysis\MarketSkillDemandContextService;
use App\Services\Recommendations\LearningPathService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class GraduationDemoMarketSnapshotSeeder extends Seeder
{
    private const FIXTURE_PATH = 'database/data/demo_backend_market_snapshot.json';

    private const CAREER_PATH_NAME = 'Backend Developer';

    private const STUDENT_EMAIL = 'leleen830@gmail.com';

    private const EXPECTED_COUNTS = [
        'postings' => 37,
        'occurrences' => 156,
        'trends' => 24,
        'skills' => 24,
        'skill_aliases' => 40,
    ];

    private const EXPECTED_MARKET = [
        'Python' => [
            'demand_score' => 72.97,
            'source_job_count' => 27,
        ],
        'SQL' => [
            'demand_score' => 54.05,
            'source_job_count' => 20,
        ],
        'Git' => [
            'demand_score' => 40.54,
            'source_job_count' => 15,
        ],
        'Flask' => [
            'demand_score' => 2.70,
            'source_job_count' => 1,
        ],
    ];

    public function run(): void
    {
        $this->ensureSafeEnvironment();

        $snapshot = $this->loadAndValidateFixture();

        $careerPathId = $this->resolveCareerPathId();

        $result = DB::transaction(function () use (
            $snapshot,
            $careerPathId
        ): array {
            $skillIdMap = $this->restoreSkills(
                $snapshot['skills']
            );

            $aliasIdMap = $this->restoreAliases(
                aliases: $snapshot['skill_aliases'],
                skillIdMap: $skillIdMap
            );

            $postingIdMap = $this->restorePostings(
                postings: $snapshot['market_job_postings'],
                careerPathId: $careerPathId
            );

            $this->restoreOccurrences(
                occurrences: $snapshot[
                    'market_job_posting_skill_occurrences'
                ],
                postingIdMap: $postingIdMap,
                skillIdMap: $skillIdMap,
                aliasIdMap: $aliasIdMap
            );

            $this->restoreTrends(
                trends: $snapshot['market_trends'],
                snapshotDate: (string) $snapshot['meta']['snapshot_date'],
                careerPathId: $careerPathId,
                skillIdMap: $skillIdMap
            );

            return [
                'skill_id_map' => $skillIdMap,
                'posting_id_map' => $postingIdMap,
            ];
        });

        $this->verifyRestoredSnapshot(
            snapshot: $snapshot,
            careerPathId: $careerPathId,
            postingIdMap: $result['posting_id_map']
        );

        $this->verifyMarketContext(
            careerPathId: $careerPathId
        );

        $this->verifyLearningPathIfAvailable(
            careerPathId: $careerPathId
        );
    }

    private function ensureSafeEnvironment(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException(
                'GraduationDemoMarketSnapshotSeeder is allowed only '
                .'in local or testing environments.'
            );
        }
    }

    private function loadAndValidateFixture(): array
    {
        $path = base_path(self::FIXTURE_PATH);

        if (! is_file($path)) {
            throw new RuntimeException(
                'Market snapshot fixture was not found: '
                .self::FIXTURE_PATH
            );
        }

        try {
            $snapshot = json_decode(
                (string) file_get_contents($path),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (Throwable $e) {
            throw new RuntimeException(
                'Market snapshot fixture contains invalid JSON: '
                .$e->getMessage(),
                previous: $e
            );
        }

        foreach ([
            'meta',
            'career_path',
            'skills',
            'skill_aliases',
            'market_job_postings',
            'market_job_posting_skill_occurrences',
            'market_trends',
        ] as $requiredKey) {
            if (! array_key_exists($requiredKey, $snapshot)) {
                throw new RuntimeException(
                    "Market snapshot is missing key: {$requiredKey}"
                );
            }
        }

        if (
            (int) ($snapshot['meta']['schema_version'] ?? 0) !== 1
        ) {
            throw new RuntimeException(
                'Unsupported market snapshot schema version.'
            );
        }

        if (
            (string) ($snapshot['career_path']['Name'] ?? '')
            !== self::CAREER_PATH_NAME
        ) {
            throw new RuntimeException(
                'The fixture is not a Backend Developer market snapshot.'
            );
        }

        $actualCounts = [
            'postings' => count(
                $snapshot['market_job_postings']
            ),
            'occurrences' => count(
                $snapshot['market_job_posting_skill_occurrences']
            ),
            'trends' => count(
                $snapshot['market_trends']
            ),
            'skills' => count(
                $snapshot['skills']
            ),
            'skill_aliases' => count(
                $snapshot['skill_aliases']
            ),
        ];

        foreach (self::EXPECTED_COUNTS as $key => $expected) {
            $metaCount = (int) (
                $snapshot['meta']['counts'][$key] ?? -1
            );

            if ($metaCount !== $expected) {
                throw new RuntimeException(
                    "Fixture meta count mismatch for {$key}: "
                    ."expected {$expected}, found {$metaCount}."
                );
            }

            if ($actualCounts[$key] !== $expected) {
                throw new RuntimeException(
                    "Fixture data count mismatch for {$key}: "
                    ."expected {$expected}, found {$actualCounts[$key]}."
                );
            }
        }

        if (
            empty($snapshot['meta']['snapshot_date'])
            || ! preg_match(
                '/^\d{4}-\d{2}-\d{2}$/',
                (string) $snapshot['meta']['snapshot_date']
            )
        ) {
            throw new RuntimeException(
                'Fixture snapshot_date is missing or invalid.'
            );
        }

        return $snapshot;
    }

    private function resolveCareerPathId(): int
    {
        $careerPathId = DB::table('career_paths')
            ->where('Name', self::CAREER_PATH_NAME)
            ->value('CareerPathID');

        if (! $careerPathId) {
            throw new RuntimeException(
                'Backend Developer career path was not found. '
                .'Seed the core career paths before this seeder.'
            );
        }

        return (int) $careerPathId;
    }

    /**
     * @return array<int, int> old fixture skill id => current DB skill id
     */
    private function restoreSkills(array $skills): array
    {
        $map = [];

        foreach ($skills as $row) {
            $oldId = (int) ($row['id'] ?? 0);
            $name = trim((string) ($row['name'] ?? ''));
            $normalizedName = trim(
                (string) ($row['normalized_name'] ?? '')
            );
            $category = trim(
                (string) ($row['category'] ?? 'Other')
            );

            if (
                $oldId <= 0
                || $name === ''
                || $normalizedName === ''
            ) {
                throw new RuntimeException(
                    'Invalid skill row in market snapshot.'
                );
            }

            $existing = DB::table('skills')
                ->where('normalized_name', $normalizedName)
                ->first();

            if (! $existing) {
                $existing = DB::table('skills')
                    ->where('name', $name)
                    ->first();
            }

            if ($existing) {
                $currentId = (int) $existing->id;

                /*
                 * Preserve the platform's canonical skill identity.
                 * Fill missing descriptive fields, but do not blindly
                 * overwrite a different canonical name/category.
                 */
                $updates = [];

                if (
                    empty($existing->normalized_name)
                    && $normalizedName !== ''
                ) {
                    $updates['normalized_name'] = $normalizedName;
                }

                if (
                    empty($existing->category)
                    && $category !== ''
                ) {
                    $updates['category'] = $category;
                }

                if (! empty($updates)) {
                    $updates['updated_at'] = now();

                    DB::table('skills')
                        ->where('id', $currentId)
                        ->update($updates);
                }
            } else {
                $currentId = (int) DB::table('skills')
                    ->insertGetId([
                        'name' => $name,
                        'category' => $category,
                        'normalized_name' => $normalizedName,
                        'created_at' => $row['created_at'] ?? now(),
                        'updated_at' => $row['updated_at'] ?? now(),
                    ]);
            }

            $map[$oldId] = $currentId;
        }

        return $map;
    }

    /**
     * @param  array<int, int>  $skillIdMap
     * @return array<int, int> old fixture alias id => current DB alias id
     */
    private function restoreAliases(
        array $aliases,
        array $skillIdMap
    ): array {
        $map = [];

        foreach ($aliases as $row) {
            $oldAliasId = (int) (
                $row['SkillAliasID'] ?? 0
            );
            $oldSkillId = (int) (
                $row['SkillID'] ?? 0
            );
            $alias = trim(
                (string) ($row['Alias'] ?? '')
            );
            $languageCode = $row['LanguageCode'] ?? null;

            if (
                $oldAliasId <= 0
                || $alias === ''
                || ! isset($skillIdMap[$oldSkillId])
            ) {
                throw new RuntimeException(
                    'Invalid skill alias row in market snapshot.'
                );
            }

            $currentSkillId = $skillIdMap[$oldSkillId];

            /*
             * Alias is globally unique in the real schema.
             */
            $existing = DB::table('skill_aliases')
                ->where('Alias', $alias)
                ->first();

            if ($existing) {
                if (
                    (int) $existing->SkillID
                    !== $currentSkillId
                ) {
                    throw new RuntimeException(
                        "Skill alias conflict for '{$alias}': "
                        .'the current database maps it to another skill.'
                    );
                }

                $currentAliasId = (int) (
                    $existing->SkillAliasID
                );
            } else {
                $currentAliasId = (int) DB::table(
                    'skill_aliases'
                )->insertGetId([
                    'SkillID' => $currentSkillId,
                    'Alias' => $alias,
                    'LanguageCode' => $languageCode,
                    'created_at' => $row['created_at'] ?? now(),
                    'updated_at' => $row['updated_at'] ?? now(),
                ], 'SkillAliasID');
            }

            $map[$oldAliasId] = $currentAliasId;
        }

        return $map;
    }

    /**
     * @return array<int, int> old fixture posting id => current DB posting id
     */
    private function restorePostings(
        array $postings,
        int $careerPathId
    ): array {
        $map = [];

        foreach ($postings as $row) {
            $oldId = (int) ($row['id'] ?? 0);

            if ($oldId <= 0) {
                throw new RuntimeException(
                    'Invalid market posting id in fixture.'
                );
            }

            $sourceName = $row['source_name'] ?? null;
            $externalId = $row['external_id'] ?? null;
            $contentHash = (string) (
                $row['content_hash'] ?? ''
            );

            if ($contentHash === '') {
                throw new RuntimeException(
                    "Posting {$oldId} has no content_hash."
                );
            }

            $byExternal = null;

            if ($sourceName && $externalId) {
                $byExternal = DB::table(
                    'market_job_postings'
                )
                    ->where('source_name', $sourceName)
                    ->where('external_id', $externalId)
                    ->first();
            }

            $byHash = DB::table('market_job_postings')
                ->where('content_hash', $contentHash)
                ->first();

            if (
                $byExternal
                && $byHash
                && (int) $byExternal->id
                    !== (int) $byHash->id
            ) {
                throw new RuntimeException(
                    "Posting identity conflict for fixture id {$oldId}."
                );
            }

            $existing = $byExternal ?: $byHash;

            $payload = [
                'source_type' => $row['source_type'] ?? 'dataset',
                'source_name' => $sourceName,
                'external_id' => $externalId,
                'url' => $row['url'] ?? null,
                'title' => (string) ($row['title'] ?? ''),
                'description' => (string) (
                    $row['description'] ?? ''
                ),
                'company_name' => $row['company_name'] ?? null,
                'location' => $row['location'] ?? null,
                'language' => $row['language'] ?? null,
                'career_path_id' => $careerPathId,
                'published_at' => $row['published_at'] ?? null,
                'imported_at' => $row['imported_at'] ?? null,
                'status' => $row['status'] ?? 'active',
                'content_hash' => $contentHash,
                'classification_status' => (
                    $row['classification_status'] ?? 'pending'
                ),
                'classification_method' => (
                    $row['classification_method'] ?? null
                ),
                'classification_score' => (
                    $row['classification_score'] ?? null
                ),
                'classification_evidence' => (
                    $this->normalizeJsonColumn(
                        $row['classification_evidence'] ?? null
                    )
                ),
                'classified_at' => (
                    $row['classified_at'] ?? null
                ),
                'updated_at' => $row['updated_at'] ?? now(),
            ];

            if ($existing) {
                $currentId = (int) $existing->id;

                DB::table('market_job_postings')
                    ->where('id', $currentId)
                    ->update($payload);
            } else {
                $payload['created_at'] = (
                    $row['created_at'] ?? now()
                );

                $currentId = (int) DB::table(
                    'market_job_postings'
                )->insertGetId($payload);
            }

            $map[$oldId] = $currentId;
        }

        return $map;
    }

    /**
     * Replaces occurrences only for the 37 fixture postings.
     * Unrelated market postings are never touched.
     *
     * @param  array<int, int>  $postingIdMap
     * @param  array<int, int>  $skillIdMap
     * @param  array<int, int>  $aliasIdMap
     */
    private function restoreOccurrences(
        array $occurrences,
        array $postingIdMap,
        array $skillIdMap,
        array $aliasIdMap
    ): void {
        $currentPostingIds = array_values(
            $postingIdMap
        );

        DB::table(
            'market_job_posting_skill_occurrences'
        )
            ->whereIn(
                'market_job_posting_id',
                $currentPostingIds
            )
            ->delete();

        $rows = [];

        foreach ($occurrences as $row) {
            $oldPostingId = (int) (
                $row['market_job_posting_id'] ?? 0
            );
            $oldSkillId = (int) (
                $row['skill_id'] ?? 0
            );
            $oldAliasId = $row['skill_alias_id'] !== null
                ? (int) $row['skill_alias_id']
                : null;

            if (
                ! isset($postingIdMap[$oldPostingId])
                || ! isset($skillIdMap[$oldSkillId])
            ) {
                throw new RuntimeException(
                    'Occurrence references an unmapped posting or skill.'
                );
            }

            $currentAliasId = null;

            if ($oldAliasId !== null) {
                if (! isset($aliasIdMap[$oldAliasId])) {
                    throw new RuntimeException(
                        "Occurrence references unmapped alias {$oldAliasId}."
                    );
                }

                $currentAliasId = $aliasIdMap[$oldAliasId];
            }

            $rows[] = [
                'market_job_posting_id' => (
                    $postingIdMap[$oldPostingId]
                ),
                'skill_id' => $skillIdMap[$oldSkillId],
                'skill_alias_id' => $currentAliasId,
                'matched_text' => (string) (
                    $row['matched_text'] ?? ''
                ),
                'language' => $row['language'] ?? null,
                'confidence' => $row['confidence'] ?? 1.00,
                'extraction_method' => (
                    $row['extraction_method'] ?? 'alias_match'
                ),
                'context' => $row['context'] ?? null,
                'created_at' => $row['created_at'] ?? now(),
                'updated_at' => $row['updated_at'] ?? now(),
            ];
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table(
                'market_job_posting_skill_occurrences'
            )->insert($chunk);
        }
    }

    /**
     * Replaces only the authoritative demo date for Backend.
     *
     * @param  array<int, int>  $skillIdMap
     */
    private function restoreTrends(
        array $trends,
        string $snapshotDate,
        int $careerPathId,
        array $skillIdMap
    ): void {
        DB::table('market_trends')
            ->where('career_path_id', $careerPathId)
            ->where('analyzed_date', $snapshotDate)
            ->delete();

        $rows = [];

        foreach ($trends as $row) {
            $oldSkillId = (int) (
                $row['skill_id'] ?? 0
            );

            if (! isset($skillIdMap[$oldSkillId])) {
                throw new RuntimeException(
                    "Trend references unmapped skill {$oldSkillId}."
                );
            }

            $rows[] = [
                'career_path_id' => $careerPathId,
                'skill_id' => $skillIdMap[$oldSkillId],
                'demand_score' => $row['demand_score'],
                'trend_direction' => (
                    $row['trend_direction'] ?? 'new'
                ),
                'source_job_count' => (
                    $row['source_job_count'] ?? 0
                ),
                'analyzed_date' => $snapshotDate,
                'created_at' => $row['created_at'] ?? now(),
                'updated_at' => $row['updated_at'] ?? now(),
            ];
        }

        DB::table('market_trends')->insert($rows);
    }

    private function normalizeJsonColumn(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            /*
             * Validate the JSON string before writing it to MySQL JSON.
             */
            json_decode(
                $value,
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            return $value;
        }

        return json_encode(
            $value,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_THROW_ON_ERROR
        );
    }

    /**
     * @param  array<int, int>  $postingIdMap
     */
    private function verifyRestoredSnapshot(
        array $snapshot,
        int $careerPathId,
        array $postingIdMap
    ): void {
        $snapshotDate = (string) (
            $snapshot['meta']['snapshot_date']
        );

        $restoredPostingIds = array_values(
            $postingIdMap
        );

        $postingCount = DB::table('market_job_postings')
            ->whereIn('id', $restoredPostingIds)
            ->count();

        $activeBackendCount = DB::table(
            'market_job_postings'
        )
            ->where('career_path_id', $careerPathId)
            ->where('status', 'active')
            ->count();

        $occurrenceCount = DB::table(
            'market_job_posting_skill_occurrences'
        )
            ->whereIn(
                'market_job_posting_id',
                $restoredPostingIds
            )
            ->count();

        $trendCount = DB::table('market_trends')
            ->where('career_path_id', $careerPathId)
            ->where('analyzed_date', $snapshotDate)
            ->count();

        if ($postingCount !== self::EXPECTED_COUNTS['postings']) {
            throw new RuntimeException(
                'Restore verification failed: expected 37 fixture '
                ."postings, found {$postingCount}."
            );
        }

        /*
         * MarketSkillDemandContextService uses ALL active postings for the
         * path as the denominator. The defense snapshot must stay 37.
         */
        if ($activeBackendCount !== self::EXPECTED_COUNTS['postings']) {
            throw new RuntimeException(
                'Restore verification found '
                ."{$activeBackendCount} active Backend market postings. "
                .'The demo snapshot requires exactly 37 so its percentages '
                .'and student messages remain truthful. Remove/archive '
                .'unrelated active Backend market postings before the demo.'
            );
        }

        if (
            $occurrenceCount
            !== self::EXPECTED_COUNTS['occurrences']
        ) {
            throw new RuntimeException(
                'Restore verification failed: expected 156 occurrences, '
                ."found {$occurrenceCount}."
            );
        }

        if ($trendCount !== self::EXPECTED_COUNTS['trends']) {
            throw new RuntimeException(
                'Restore verification failed: expected 24 trends, '
                ."found {$trendCount}."
            );
        }

        $this->command?->newLine();
        $this->command?->info(
            'Real Backend market snapshot restored successfully.'
        );
        $this->command?->line(
            "Snapshot date: {$snapshotDate}"
        );
        $this->command?->table(
            ['Metric', 'Restored'],
            [
                ['Active Backend postings', $activeBackendCount],
                ['Snapshot occurrences', $occurrenceCount],
                ['Snapshot trends', $trendCount],
            ]
        );
    }

    private function verifyMarketContext(
        int $careerPathId
    ): void {
        $skillIds = [];

        foreach (array_keys(self::EXPECTED_MARKET) as $skillName) {
            $skillId = DB::table('skills')
                ->where('name', $skillName)
                ->value('id');

            if (! $skillId) {
                throw new RuntimeException(
                    "Verification skill {$skillName} was not found."
                );
            }

            $skillIds[$skillName] = (int) $skillId;
        }

        /** @var MarketSkillDemandContextService $service */
        $service = app(
            MarketSkillDemandContextService::class
        );

        $contexts = $service->getForSkills(
            careerPathId: $careerPathId,
            skillIds: array_values($skillIds)
        );

        $rows = [];

        foreach (self::EXPECTED_MARKET as $skillName => $expected) {
            $skillId = $skillIds[$skillName];
            $context = $contexts[$skillId] ?? null;

            if (
                ! is_array($context)
                || ! ($context['available'] ?? false)
            ) {
                throw new RuntimeException(
                    "Market context is unavailable for {$skillName}."
                );
            }

            $actualDemand = (float) (
                $context['demand_score'] ?? -1
            );
            $actualSourceCount = (int) (
                $context['source_job_count'] ?? -1
            );
            $actualSampleSize = (int) (
                $context['sample_size'] ?? -1
            );

            if (
                abs(
                    $actualDemand
                    - (float) $expected['demand_score']
                ) > 0.001
            ) {
                throw new RuntimeException(
                    "{$skillName} market demand mismatch: expected "
                    .$expected['demand_score']
                    .", got {$actualDemand}."
                );
            }

            if (
                $actualSourceCount
                !== (int) $expected['source_job_count']
            ) {
                throw new RuntimeException(
                    "{$skillName} source job count mismatch."
                );
            }

            if (
                $actualSampleSize
                !== self::EXPECTED_COUNTS['postings']
            ) {
                throw new RuntimeException(
                    "{$skillName} market sample size mismatch: expected "
                    ."37, got {$actualSampleSize}."
                );
            }

            $rows[] = [
                $skillName,
                number_format($actualDemand, 2).'%',
                $actualSourceCount.'/'.$actualSampleSize,
                $context['demand_level'] ?? '-',
                $context['trend_direction'] ?? '-',
            ];
        }

        $this->command?->info(
            'Market context verification passed.'
        );
        $this->command?->table(
            [
                'Skill',
                'Demand',
                'Jobs',
                'Demand level',
                'Trend',
            ],
            $rows
        );
    }

    private function verifyLearningPathIfAvailable(
        int $careerPathId
    ): void {
        $studentId = DB::table('users')
            ->where('email', self::STUDENT_EMAIL)
            ->value('id');

        if (! $studentId) {
            $this->command?->warn(
                'Learning-path verification skipped: demo student '
                .'does not exist yet.'
            );

            return;
        }

        $session = AssessmentSession::query()
            ->where('UserID', (int) $studentId)
            ->where('CareerPathID', $careerPathId)
            ->where('Status', AssessmentSession::STATUS_COMPLETED)
            ->latest('AssessmentSessionID')
            ->first();

        if (! $session) {
            $this->command?->warn(
                'Learning-path verification skipped: no completed '
                .'Backend assessment exists for the demo student yet.'
            );

            return;
        }

        /** @var LearningPathService $learningPathService */
        $learningPathService = app(
            LearningPathService::class
        );

        $path = $learningPathService->generate(
            $session
        );

        $skillNames = collect($path)
            ->pluck('skill_name')
            ->values()
            ->all();

        if (
            ($skillNames[0] ?? null) !== 'Git'
            || ($skillNames[1] ?? null) !== 'Flask'
        ) {
            throw new RuntimeException(
                'Learning path verification failed. '
                .'Expected Git first and Flask second, got: '
                .implode(', ', $skillNames)
            );
        }

        $rows = collect($path)
            ->map(function (array $item): array {
                $market = $item['market'] ?? [];

                return [
                    $item['skill_name'] ?? '-',
                    $item['current_level'] ?? '-',
                    $item['target_level'] ?? '-',
                    $item['priority'] ?? '-',
                    isset($market['demand_score'])
                        ? $market['demand_score'].'%'
                        : 'N/A',
                ];
            })
            ->all();

        $this->command?->info(
            'Learning-path ranking verification passed.'
        );
        $this->command?->table(
            [
                'Ranked skill',
                'Current',
                'Target',
                'Gap priority',
                'Market demand',
            ],
            $rows
        );

        $this->command?->info(
            'Expected demo order confirmed: #1 Git, #2 Flask.'
        );
    }
}
