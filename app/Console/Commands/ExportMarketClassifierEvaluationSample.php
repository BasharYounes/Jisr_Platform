<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ExportMarketClassifierEvaluationSample extends Command
{
    protected $signature =
        'market:export-classifier-evaluation
        {--per-status=10 : Number of jobs selected from every status}
        {--source=arbeitnow : Job source name}';

    protected $description =
        'Export a small real job sample for manual classifier evaluation';

    public function handle(): int
    {
        $perStatus = max(
            1,
            (int) $this->option('per-status')
        );

        $sourceName = trim(
            (string) $this->option('source')
        );

        $statuses = [
            'classified',
            'ambiguous',
            'out_of_scope',
            'insufficient_evidence',
        ];

        $rows = collect();

        foreach ($statuses as $status) {
            $jobs = DB::table(
                'market_job_postings as job'
            )
                ->leftJoin(
                    'career_paths as path',
                    'path.CareerPathID',
                    '=',
                    'job.career_path_id'
                )
                ->where(
                    'job.source_name',
                    $sourceName
                )
                ->where(
                    'job.classification_status',
                    $status
                )
                ->select([
                    'job.id',
                    'job.title',
                    'job.description',
                    'job.classification_status',
                    'job.classification_score',
                    'path.Name as career_path',
                ])
                ->inRandomOrder()
                ->limit($perStatus)
                ->get();

            $rows = $rows->concat($jobs);
        }

        if ($rows->isEmpty()) {
            $this->error(
                'No job postings were found.'
            );

            return self::FAILURE;
        }

        $directory = storage_path(
            'app/market-analysis'
        );

        File::ensureDirectoryExists($directory);

        $filePath = $directory .
            DIRECTORY_SEPARATOR .
            'classifier_evaluation_sample.csv';

        $handle = fopen($filePath, 'w');

        if ($handle === false) {
            $this->error(
                'Unable to create evaluation file.'
            );

            return self::FAILURE;
        }

        /*
         * UTF-8 BOM makes Arabic and German characters
         * display correctly when opening the CSV in Excel.
         */
        fwrite($handle, "\xEF\xBB\xBF");

        fputcsv($handle, [
            'job_id',
            'title',
            'description_excerpt',
            'predicted_label',
            'classification_score',
            'expected_label',
            'review_notes',
        ]);

        foreach ($rows as $job) {
            fputcsv($handle, [
                $job->id,
                $job->title,

                Str::limit(
                    $this->cleanDescription(
                        (string) $job->description
                    ),
                    1500,
                    '...'
                ),

                $this->resolvePredictedLabel(
                    (string) $job->classification_status,
                    $job->career_path
                ),

                $job->classification_score,

                /*
                 * This column is intentionally empty.
                 * It will be completed manually.
                 */
                '',

                '',
            ]);
        }

        fclose($handle);

        $this->info(
            'Evaluation sample exported successfully.'
        );

        $this->table(
            ['Item', 'Value'],
            [
                ['Jobs exported', $rows->count()],
                ['Jobs per status', $perStatus],
                ['Source', $sourceName],
                ['File', $filePath],
            ]
        );

        return self::SUCCESS;
    }

    private function resolvePredictedLabel(
        string $status,
        ?string $careerPath
    ): string {
        if ($status !== 'classified') {
            return $status;
        }

        return match ($careerPath) {
            'Backend Developer' => 'backend',
            'Frontend Developer' => 'frontend',
            'Mobile Developer' => 'mobile',
            'DevOps Engineer' => 'devops',
            default => 'insufficient_evidence',
        };
    }

    private function cleanDescription(
        string $description
    ): string {
        $description = html_entity_decode(
            $description,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        $description = strip_tags($description);

        return trim(
            preg_replace(
                '/\s+/u',
                ' ',
                $description
            ) ?? $description
        );
    }
}
