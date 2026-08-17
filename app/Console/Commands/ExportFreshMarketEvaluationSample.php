<?php

namespace App\Console\Commands;

use App\Models\MarketJobPosting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class ExportFreshMarketEvaluationSample extends Command
{
    protected $signature = 'market:export-fresh-evaluation-sample
        {--count=20 : Number of new job postings}
        {--source=arbeitnow : Job source}
        {--exclude-file=storage/app/market-analysis/classifier_evaluation_sample_47_completed.csv : CSV containing previously evaluated job IDs}
        {--output=storage/app/market-analysis/classifier_evaluation_sample_20_blind.csv : Output CSV path}';

    protected $description =
        'Export a blind random sample of new market job postings for manual labeling';

    public function handle(): int
    {
        try {
            $count = max(1, (int) $this->option('count'));
            $source = trim((string) $this->option('source'));

            $excludePath = $this->resolvePath(
                trim((string) $this->option('exclude-file')),
                false
            );

            $outputPath = $this->resolvePath(
                trim((string) $this->option('output')),
                true
            );

            $excludedIds = $this->readExcludedJobIds(
                $excludePath
            );

            $query = MarketJobPosting::query()
                ->select([
                    'id',
                    'title',
                    'description',
                ])
                ->whereNotIn('id', $excludedIds)
                ->whereNotNull('title')
                ->where('title', '<>', '')
                ->whereNotNull('description')
                ->where('description', '<>', '');

            if ($source !== '') {
                $query->where(
                    'source_name',
                    $source
                );
            }

            $jobs = $query
                ->inRandomOrder()
                ->limit($count)
                ->get();

            if ($jobs->count() < $count) {
                throw new RuntimeException(
                    sprintf(
                        'Only %d eligible new jobs were found; %d were requested.',
                        $jobs->count(),
                        $count
                    )
                );
            }

            File::ensureDirectoryExists(
                dirname($outputPath)
            );

            $handle = fopen($outputPath, 'w');

            if ($handle === false) {
                throw new RuntimeException(
                    'Unable to create output CSV.'
                );
            }

            try {
                fwrite($handle, "\xEF\xBB\xBF");

                fputcsv($handle, [
                    'job_id',
                    'title',
                    'description_excerpt',
                    'expected_label',
                    'review_notes',
                ]);

                foreach ($jobs as $job) {
                    fputcsv($handle, [
                        (string) $job->id,
                        trim((string) $job->title),
                        $this->cleanDescription(
                            (string) $job->description
                        ),
                        '',
                        '',
                    ]);
                }
            } finally {
                fclose($handle);
            }

            $this->info(
                sprintf(
                    'Fresh blind sample created successfully: %d jobs',
                    $jobs->count()
                )
            );

            $this->line(
                'Excluded previous IDs: '
                .count($excludedIds)
            );

            $this->line(
                'Output: '.$outputPath
            );

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error(
                $exception->getMessage()
            );

            return self::FAILURE;
        }
    }

    private function resolvePath(
        string $configuredPath,
        bool $allowMissing
    ): string {
        if ($configuredPath === '') {
            throw new RuntimeException(
                'A file path cannot be empty.'
            );
        }

        if (File::exists($configuredPath)) {
            return realpath($configuredPath)
                ?: $configuredPath;
        }

        $projectPath = base_path(
            $configuredPath
        );

        if (
            $allowMissing ||
            File::exists($projectPath)
        ) {
            return $projectPath;
        }

        throw new RuntimeException(
            'File was not found: '
            .$configuredPath
        );
    }

    /**
     * @return array<int, int>
     */
    private function readExcludedJobIds(
        string $path
    ): array {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException(
                'Unable to open exclusion CSV.'
            );
        }

        try {
            $headers = fgetcsv($handle);

            if ($headers === false) {
                throw new RuntimeException(
                    'Exclusion CSV header is missing.'
                );
            }

            $headers[0] = str_replace(
                "\xEF\xBB\xBF",
                '',
                (string) $headers[0]
            );

            $jobIdIndex = array_search(
                'job_id',
                $headers,
                true
            );

            if ($jobIdIndex === false) {
                throw new RuntimeException(
                    'The exclusion CSV must contain a job_id column.'
                );
            }

            $ids = [];

            while (($row = fgetcsv($handle)) !== false) {
                $jobId = (int) (
                    $row[$jobIdIndex] ?? 0
                );

                if ($jobId > 0) {
                    $ids[] = $jobId;
                }
            }

            return array_values(
                array_unique($ids)
            );
        } finally {
            fclose($handle);
        }
    }

    private function cleanDescription(
        string $description
    ): string {
        $plainText = html_entity_decode(
            strip_tags($description),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        $plainText = preg_replace(
            '/\s+/u',
            ' ',
            $plainText
        ) ?? $plainText;

        return Str::limit(
            trim($plainText),
            4000,
            '...'
        );
    }
}
