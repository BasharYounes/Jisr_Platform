<?php

namespace App\Console\Commands;

use App\Models\CareerPath;
use App\Models\MarketJobPosting;
use App\Services\MarketAnalysis\MarketSkillExtractionService;
use Illuminate\Console\Command;

class ReanalyzeMarketJobPostings extends Command
{
    protected $signature = 'market:reanalyze-job-postings
        {--career-path-id= : Reanalyze postings for a specific career path}
        {--source-name= : Reanalyze postings from a specific source}
        {--status=active : Posting status to reanalyze, use "all" to include all statuses}
        {--chunk=100 : Number of postings processed per chunk}';

    protected $description = 'Reanalyze market job postings and refresh extracted skill occurrences.';

    public function handle(MarketSkillExtractionService $skillExtractionService): int
    {
        $careerPathId = $this->option('career-path-id');
        $sourceName = $this->option('source-name');
        $status = $this->option('status') ?: 'active';
        $chunkSize = max(1, (int) $this->option('chunk'));

        if ($careerPathId !== null) {
            $careerPath = CareerPath::query()
                ->where('CareerPathID', (int) $careerPathId)
                ->first();

            if (! $careerPath) {
                $this->error("Career path not found: {$careerPathId}");

                return self::FAILURE;
            }
        }

        $query = MarketJobPosting::query()
            ->when($careerPathId !== null, function ($query) use ($careerPathId) {
                $query->where('career_path_id', (int) $careerPathId);
            })
            ->when($sourceName, function ($query) use ($sourceName) {
                $query->where('source_name', $sourceName);
            })
            ->when($status !== 'all', function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->orderBy('id');

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->warn('No market job postings found for the selected filters.');

            return self::SUCCESS;
        }

        $this->info("Reanalyzing {$total} market job postings...");

        $processed = 0;
        $failed = 0;

        $progressBar = $this->output->createProgressBar($total);
        $progressBar->start();

        $query->chunkById($chunkSize, function ($postings) use (
            $skillExtractionService,
            &$processed,
            &$failed,
            $progressBar
        ) {
            foreach ($postings as $posting) {
                try {
                    $skillExtractionService->extractForJobPosting($posting);
                    $processed++;
                } catch (\Throwable $exception) {
                    $failed++;
                    $this->newLine();
                    $this->warn("Failed posting #{$posting->id}: {$exception->getMessage()}");
                }

                $progressBar->advance();
            }
        });

        $progressBar->finish();
        $this->newLine(2);

        $this->info("Processed: {$processed}");
        $this->info("Failed: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
