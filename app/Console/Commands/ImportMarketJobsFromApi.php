<?php

namespace App\Console\Commands;

use App\Interfaces\JobSourceAdapterInterface;
use App\Services\MarketAnalysis\MarketJobPostingImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

class ImportMarketJobsFromApi extends Command
{
    protected $signature = 'market:import-api-jobs
        {--pages=1 : Number of API pages to fetch}
        {--limit=20 : Maximum number of job postings to process}
        {--dry-run : Fetch and display jobs without saving them}';

    protected $description =
        'Import job postings from the configured external API source.';

    public function handle(
        JobSourceAdapterInterface $adapter,
        MarketJobPostingImportService $importService
    ): int {
        $pages = max(1, (int) $this->option('pages'));
        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');

        $processed = 0;
        $created = 0;
        $updated = 0;
        $failed = 0;

        $this->info('Starting external market jobs import...');
        $this->line('Source: ' . $adapter->sourceName());
        $this->line("Pages: {$pages}");
        $this->line("Limit: {$limit}");

        if ($dryRun) {
            $this->warn(
                'Dry-run mode enabled: no database records will be changed.'
            );
        }

        for ($page = 1; $page <= $pages; $page++) {
            if ($processed >= $limit) {
                break;
            }

            $this->newLine();
            $this->info("Fetching page {$page}...");

            try {
                $result = $adapter->fetchPage($page);
            } catch (Throwable $exception) {
                $this->error(
                    "Failed to fetch page {$page}: "
                    . $exception->getMessage()
                );

                return self::FAILURE;
            }

            $jobs = $result['jobs'] ?? [];

            if (! is_array($jobs)) {
                $this->error(
                    "Invalid jobs data received on page {$page}."
                );

                return self::FAILURE;
            }

            foreach ($jobs as $job) {
                if ($processed >= $limit) {
                    break;
                }

                $processed++;

                $title = (string) ($job['title'] ?? 'Untitled job');
                $externalId = (string) ($job['external_id'] ?? '-');

                if ($dryRun) {
                    $this->line(sprintf(
                        '[%d] %s | %s',
                        $processed,
                        $externalId,
                        Str::limit($title, 80)
                    ));

                    continue;
                }

                try {
                    /*
                     * لا نصنف المسار الآن حتى لا نخمن بشكل خاطئ.
                     */
                    $job['career_path_id'] = null;

                    $posting = $importService->import($job);

                    if ($posting->wasRecentlyCreated) {
                        $created++;
                    } else {
                        $updated++;
                    }
                } catch (Throwable $exception) {
                    $failed++;

                    $this->warn(sprintf(
                        'Failed job [%s]: %s',
                        $externalId,
                        $exception->getMessage()
                    ));
                }
            }

            $hasMore = (bool) ($result['has_more'] ?? false);

            if (! $hasMore) {
                $this->line('No more API pages available.');
                break;
            }
        }

        $this->newLine();
        $this->info('Import process completed.');
        $this->line("Processed: {$processed}");

        if ($dryRun) {
            $this->warn('Saved: 0 because dry-run mode was used.');

            return self::SUCCESS;
        }

        $this->line("Created: {$created}");
        $this->line("Updated: {$updated}");
        $this->line("Failed: {$failed}");

        return $failed > 0
            ? self::FAILURE
            : self::SUCCESS;
    }
}
