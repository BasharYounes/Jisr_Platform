<?php

namespace App\Console\Commands;

use App\Models\MarketJobPosting;
use App\Services\MarketAnalysis\GeminiMarketJobCareerPathClassifierService;
use App\Services\MarketAnalysis\MarketJobCareerPathClassifierService;
use App\Services\MarketAnalysis\MarketSkillExtractionService;
use Illuminate\Console\Command;
use Throwable;

final class AnalyzeMarketJobPostings extends Command
{
    protected $signature = 'market:analyze-job-postings
        {--limit=100 : Maximum number of postings to analyze}
        {--status=pending : Current classification status to process}
        {--source= : Optional source name filter}';

    protected $description =
        'Re-extract skills and classify market job postings into career paths';

    public function handle(
        MarketSkillExtractionService $skillExtractionService,

        MarketJobCareerPathClassifierService $classifierService,

        GeminiMarketJobCareerPathClassifierService $geminiClassifierService,
    ): int {
        $limit = max(
            1,
            (int) $this->option('limit')
        );

        $status = trim(
            (string) $this->option('status')
        );

        $source = trim(
            (string) $this->option('source')
        );

        $query = MarketJobPosting::query()
            ->where('classification_status', $status)
            ->orderBy('id');

        if ($source !== '') {
            $query->where('source_name', $source);
        }

        $jobPostings = $query
            ->limit($limit)
            ->get();

        if ($jobPostings->isEmpty()) {
            $this->info(
                'No matching job postings were found.'
            );

            return self::SUCCESS;
        }

        $counts = [
            'processed' => 0,
            'classified' => 0,
            'ambiguous' => 0,
            'out_of_scope' => 0,
            'insufficient_evidence' => 0,
            'failed' => 0,
        ];

        $this->info(
            sprintf(
                'Analyzing %d job postings...',
                $jobPostings->count()
            )
        );

        $progressBar = $this->output->createProgressBar(
            $jobPostings->count()
        );

        $progressBar->start();

        foreach ($jobPostings as $jobPosting) {
            try {
                /*
                 * Re-run extraction so old postings benefit
                 * from the newest normalizer and skill aliases.
                 */
                $skillExtractionService
                    ->extractForJobPosting($jobPosting);

                /*
                * Rules classifier:
                */
                // $result = $classifierService->classify(
                //     $jobPosting->fresh()
                // );

                /*
                * Gemini classifier:
                */
                $result = $geminiClassifierService->classify(
                    $jobPosting->fresh()
                );

                $classificationStatus = (string) (
                    $result['status']
                    ?? 'insufficient_evidence'
                );

                if (
                    array_key_exists(
                        $classificationStatus,
                        $counts
                    )
                ) {
                    $counts[$classificationStatus]++;
                }

                $counts['processed']++;
            } catch (Throwable $exception) {
                $counts['failed']++;

                report($exception);

                $this->newLine();

                $this->error(
                    sprintf(
                        'Failed posting ID %d: %s',
                        $jobPosting->id,
                        $exception->getMessage()
                    )
                );
            } finally {
                $progressBar->advance();
            }
        }

        $progressBar->finish();

        $this->newLine(2);

        $this->table(
            ['Result', 'Count'],
            [
                ['Processed', $counts['processed']],
                ['Classified', $counts['classified']],
                ['Ambiguous', $counts['ambiguous']],
                ['Out of scope', $counts['out_of_scope']],
                [
                    'Insufficient evidence',
                    $counts['insufficient_evidence'],
                ],
                ['Failed', $counts['failed']],
            ]
        );

        return $counts['failed'] > 0
            ? self::FAILURE
            : self::SUCCESS;
    }
}
