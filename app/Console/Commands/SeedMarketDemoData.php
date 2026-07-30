<?php

namespace App\Console\Commands;

use Database\Seeders\MarketAnalysisFrontendSeeder;
use Database\Seeders\MarketAnalysisMobileSeeder;
use Database\Seeders\MarketAnalysisSkillDictionarySeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedMarketDemoData extends Command
{
    protected $signature = 'market:seed-demo-data
        {--date= : Snapshot date, example: 2026-07-23}
        {--fresh : Delete existing market postings and trends for demo career paths before importing demo data}';

    protected $description = 'Seed market analysis demo dictionaries, import demo job postings, and create trend snapshots.';

    public function handle(): int
    {
        $snapshotDate = $this->option('date');
        $fresh = (bool) $this->option('fresh');

        $this->info('Preparing labor market analysis demo data...');

        $this->seedMarketDictionaries();

        $careerPathConfigs = [
            [
                'name' => 'Backend Developer',
                'description' => 'Backend development track focused on server-side applications, APIs, databases, and backend engineering practices.',
                'dataset' => database_path('seeders/data/backend_test_jobs.json'),
                'source_name' => 'backend_test_dataset',
            ],
            [
                'name' => 'Frontend Developer',
                'description' => 'Frontend development track focused on user interfaces, web technologies, responsive design, and client-side application development.',
                'dataset' => database_path('seeders/data/frontend_test_jobs.json'),
                'source_name' => 'frontend_test_dataset',
            ],
            [
                'name' => 'Mobile Developer',
                'description' => 'Mobile development track focused on Flutter, mobile platforms, APIs, Firebase, and application state management.',
                'dataset' => database_path('seeders/data/mobile_test_jobs.json'),
                'source_name' => 'mobile_test_dataset',
            ],
        ];

        foreach ($careerPathConfigs as $config) {
            $careerPathId = $this->ensureCareerPath(
                name: $config['name'],
                description: $config['description']
            );

            if (! file_exists($config['dataset'])) {
                $this->error("Dataset file not found: {$config['dataset']}");

                return self::FAILURE;
            }

            $this->line('');
            $this->info("Processing {$config['name']}...");

            if ($fresh) {
                $this->warn("Fresh mode enabled. Clearing existing market data for {$config['name']}...");
                $this->resetCareerPathMarketData($careerPathId);
            }

            $this->call('market:import-job-postings', [
                'file' => $config['dataset'],
                '--career-path-id' => $careerPathId,
                '--source-name' => $config['source_name'],
            ]);

            $this->call('market:reanalyze-job-postings', [
                '--career-path-id' => $careerPathId,
            ]);

            $snapshotArguments = [
                '--career-path-id' => $careerPathId,
            ];

            if ($snapshotDate) {
                $snapshotArguments['--date'] = $snapshotDate;
            }

            $this->call('market:snapshot-trends', $snapshotArguments);

            $this->printCareerPathSummary(
                careerPathId: $careerPathId,
                careerPathName: $config['name']
            );
        }

        $this->line('');
        $this->info('Labor market analysis demo data prepared successfully.');

        return self::SUCCESS;
    }

    private function seedMarketDictionaries(): void
    {
        $this->info('Seeding market analysis dictionaries...');

        $this->call('db:seed', [
            '--class' => MarketAnalysisSkillDictionarySeeder::class,
        ]);

        $this->call('db:seed', [
            '--class' => MarketAnalysisFrontendSeeder::class,
        ]);

        $this->call('db:seed', [
            '--class' => MarketAnalysisMobileSeeder::class,
        ]);
    }

    private function ensureCareerPath(string $name, string $description): int
    {
        $careerPathId = DB::table('career_paths')
            ->where('Name', $name)
            ->value('CareerPathID');

        if ($careerPathId !== null) {
            DB::table('career_paths')
                ->where('CareerPathID', $careerPathId)
                ->update([
                    'Description' => $description,
                    'updated_at' => now(),
                ]);

            return (int) $careerPathId;
        }

        return (int) DB::table('career_paths')->insertGetId([
            'Name' => $name,
            'Description' => $description,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function getCareerPathId(string $careerPathName): ?int
    {
        $careerPathId = DB::table('career_paths')
            ->where('Name', $careerPathName)
            ->value('CareerPathID');

        return $careerPathId !== null
            ? (int) $careerPathId
            : null;
    }

    private function resetCareerPathMarketData(int $careerPathId): void
    {
        DB::transaction(function () use ($careerPathId): void {
            DB::table('market_trends')
                ->where('career_path_id', $careerPathId)
                ->delete();

            DB::table('market_job_postings')
                ->where('career_path_id', $careerPathId)
                ->delete();
        });
    }

    private function printCareerPathSummary(int $careerPathId, string $careerPathName): void
    {
        $jobPostingCount = DB::table('market_job_postings')
            ->where('career_path_id', $careerPathId)
            ->where('status', 'active')
            ->count();

        $latestSnapshotDate = DB::table('market_trends')
            ->where('career_path_id', $careerPathId)
            ->max('analyzed_date');

        $trendCount = $latestSnapshotDate
            ? DB::table('market_trends')
                ->where('career_path_id', $careerPathId)
                ->where('analyzed_date', $latestSnapshotDate)
                ->count()
            : 0;

        $this->table(
            ['Career Path', 'Job Postings', 'Latest Snapshot', 'Trend Skills'],
            [
                [
                    $careerPathName,
                    $jobPostingCount,
                    $latestSnapshotDate ?? 'N/A',
                    $trendCount,
                ],
            ]
        );
    }
}
