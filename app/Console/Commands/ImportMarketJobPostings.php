<?php

namespace App\Console\Commands;

use App\Models\CareerPath;
use App\Services\MarketAnalysis\MarketJobPostingImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ImportMarketJobPostings extends Command
{
    protected $signature = 'market:import-job-postings
        {file : Path to JSON dataset file}
        {--career-path-id= : Career path ID to attach imported postings to}
        {--source-name=dataset : Source name stored with imported postings}';

    protected $description = 'Import market job postings from a JSON dataset and extract skills using aliases.';

    public function handle(MarketJobPostingImportService $importService): int
    {
        $filePath = $this->argument('file');
        $careerPathId = $this->option('career-path-id');
        $sourceName = $this->option('source-name') ?: 'dataset';

        if (! File::exists($filePath)) {
            $this->error("Dataset file not found: {$filePath}");

            return self::FAILURE;
        }

        if ($careerPathId !== null) {
            $careerPath = CareerPath::query()
                ->where('CareerPathID', (int) $careerPathId)
                ->first();

            if (! $careerPath) {
                $this->error("Career path not found: {$careerPathId}");

                return self::FAILURE;
            }
        }

        $rawContent = File::get($filePath);
        $items = json_decode($rawContent, true);

        if (! is_array($items)) {
            $this->error('Invalid JSON dataset. Expected an array of job postings.');

            return self::FAILURE;
        }

        $imported = 0;
        $failed = 0;

        $this->info('Starting market job postings import...');
        $this->info('Total records: '.count($items));

        foreach ($items as $index => $item) {
            try {
                $title = $item['title'] ?? $item['job_title'] ?? null;
                $description = $item['description'] ?? $item['job_description'] ?? null;

                if (! $title || ! $description) {
                    $failed++;
                    $this->warn("Skipped record #{$index}: missing title or description.");

                    continue;
                }

                $importService->import([
                    'source_type' => 'dataset',
                    'source_name' => $sourceName,
                    'external_id' => $item['external_id'] ?? $item['id'] ?? "dataset-{$index}",
                    'url' => $item['url'] ?? null,
                    'title' => $title,
                    'description' => $description,
                    'company_name' => $item['company_name'] ?? $item['company'] ?? null,
                    'location' => $item['location'] ?? null,
                    'language' => $item['language'] ?? null,
                    'career_path_id' => $careerPathId ? (int) $careerPathId : null,
                    'published_at' => $item['published_at'] ?? $item['date_posted'] ?? null,
                    'status' => 'active',
                ]);

                $imported++;
            } catch (\Throwable $exception) {
                $failed++;
                $this->warn("Failed record #{$index}: ".$exception->getMessage());
            }
        }

        $this->newLine();
        $this->info("Imported/updated: {$imported}");
        $this->info("Failed/skipped: {$failed}");

        return self::SUCCESS;
    }
}
