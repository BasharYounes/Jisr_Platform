<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ExportBackendMarketDemoSnapshot extends Command
{
    protected $signature = 'market:export-demo-snapshot
        {--career-path-id=11 : Career path ID to export}
        {--output=demo_backend_market_snapshot.json : Output filename inside storage/app}';

    protected $description = 'Export a reproducible market-analysis snapshot for the graduation demo.';

    public function handle(): int
    {
        $careerPathId = (int) $this->option('career-path-id');
        $outputFile = trim((string) $this->option('output'));

        if ($careerPathId <= 0) {
            $this->error('Invalid career path ID.');

            return self::FAILURE;
        }

        if ($outputFile === '') {
            $this->error('Output filename cannot be empty.');

            return self::FAILURE;
        }

        $careerPath = DB::table('career_paths')
            ->where('CareerPathID', $careerPathId)
            ->first();

        if (! $careerPath) {
            $this->error("Career path #{$careerPathId} was not found.");

            return self::FAILURE;
        }

        $latestSnapshotDate = DB::table('market_trends')
            ->where('career_path_id', $careerPathId)
            ->max('analyzed_date');

        if ($latestSnapshotDate === null) {
            $this->error(
                "No market trend snapshot exists for career path #{$careerPathId}. "
                ."Run: php artisan market:snapshot-trends --career-path-id={$careerPathId}"
            );

            return self::FAILURE;
        }

        $postings = DB::table('market_job_postings')
            ->where('career_path_id', $careerPathId)
            ->where('status', 'active')
            ->orderBy('id')
            ->get();

        $postingIds = $postings
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $occurrences = empty($postingIds)
            ? collect()
            : DB::table('market_job_posting_skill_occurrences')
                ->whereIn('market_job_posting_id', $postingIds)
                ->orderBy('market_job_posting_id')
                ->get();

        $trends = DB::table('market_trends')
            ->where('career_path_id', $careerPathId)
            ->whereDate('analyzed_date', $latestSnapshotDate)
            ->orderBy('skill_id')
            ->get();

        $skillIds = $occurrences
            ->pluck('skill_id')
            ->merge($trends->pluck('skill_id'))
            ->filter(fn ($id) => $id !== null)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $skills = empty($skillIds)
            ? collect()
            : DB::table('skills')
                ->whereIn('id', $skillIds)
                ->orderBy('id')
                ->get();

        $aliasIds = $occurrences
            ->pluck('skill_alias_id')
            ->filter(fn ($id) => $id !== null)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $skillAliases = empty($aliasIds)
            ? collect()
            : DB::table('skill_aliases')
                ->whereIn('SkillAliasID', $aliasIds)
                ->orderBy('SkillAliasID')
                ->get();

        $payload = [
            'meta' => [
                'schema_version' => 1,
                'exported_at' => now()->toIso8601String(),
                'career_path_id' => $careerPathId,
                'snapshot_date' => (string) $latestSnapshotDate,
                'counts' => [
                    'postings' => $postings->count(),
                    'occurrences' => $occurrences->count(),
                    'trends' => $trends->count(),
                    'skills' => $skills->count(),
                    'skill_aliases' => $skillAliases->count(),
                ],
            ],
            'career_path' => $careerPath,
            'skills' => $skills->values(),
            'skill_aliases' => $skillAliases->values(),
            'market_job_postings' => $postings->values(),
            'market_job_posting_skill_occurrences' => $occurrences->values(),
            'market_trends' => $trends->values(),
        ];

        $json = json_encode(
            $payload,
            JSON_PRETTY_PRINT
            | JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_THROW_ON_ERROR
        );

        $outputPath = storage_path('app/'.$outputFile);

        File::ensureDirectoryExists(dirname($outputPath));
        File::put($outputPath, $json);

        $this->newLine();
        $this->info('Market demo snapshot exported successfully.');
        $this->line("Career path: {$careerPath->Name} (#{$careerPathId})");
        $this->line("Snapshot date: {$latestSnapshotDate}");
        $this->line("Postings: {$postings->count()}");
        $this->line("Skill occurrences: {$occurrences->count()}");
        $this->line("Trends: {$trends->count()}");
        $this->line("Skills: {$skills->count()}");
        $this->line("Skill aliases: {$skillAliases->count()}");
        $this->line("File: {$outputPath}");

        return self::SUCCESS;
    }
}
