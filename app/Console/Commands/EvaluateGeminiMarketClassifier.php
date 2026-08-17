<?php

namespace App\Console\Commands;

use App\Models\MarketJobPosting;
use App\Services\MarketAnalysis\GeminiMarketJobCareerPathClassifierService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

final class EvaluateGeminiMarketClassifier extends Command
{
    protected $signature = 'market:evaluate-gemini-classifier
        {--file=storage/app/market-analysis/classifier_evaluation_sample_47_completed.csv : Evaluation CSV path}
        {--delay-ms=500 : Delay between Gemini calls in milliseconds}';

    protected $description =
        'Evaluate Gemini market job classification against manually reviewed labels without changing database data';

    /**
     * @var array<int, string>
     */
    private const LABELS = [
        'backend',
        'frontend',
        'mobile',
        'devops',
        'ambiguous',
        'out_of_scope',
        'insufficient_evidence',
    ];

    public function handle(
        GeminiMarketJobCareerPathClassifierService $classifierService,
    ): int {
        try {
            $inputPath = $this->resolveInputPath(
                trim((string) $this->option('file'))
            );

            $rows = $this->readCsv($inputPath);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($rows === []) {
            $this->error('The evaluation CSV contains no data rows.');

            return self::FAILURE;
        }

        $delayMilliseconds = max(
            0,
            (int) $this->option('delay-ms')
        );

        $evaluatedRows = [];
        $outputRows = [];
        $failed = 0;
        $fallbackCount = 0;

        $this->info(
            sprintf(
                'Evaluating Gemini on %d manually reviewed job postings...',
                count($rows)
            )
        );

        $progressBar = $this->output->createProgressBar(
            count($rows)
        );

        $progressBar->start();

        foreach ($rows as $row) {
            $jobId = (int) ($row['job_id'] ?? 0);
            $expectedLabel = trim(
                (string) ($row['expected_label'] ?? '')
            );

            $geminiPredictedLabel = '';
            $geminiMethod = '';
            $geminiStatus = '';
            $geminiCareerPath = '';
            $geminiIsCorrect = '';
            $geminiError = '';

            try {
                if (
                    $jobId <= 0 ||
                    ! in_array(
                        $expectedLabel,
                        self::LABELS,
                        true
                    )
                ) {
                    throw new RuntimeException(
                        sprintf(
                            'Invalid job_id or expected_label in CSV row for job [%s].',
                            (string) ($row['job_id'] ?? '')
                        )
                    );
                }

                $jobPosting = MarketJobPosting::query()
                    ->findOrFail($jobId);

                /*
                 * The classifier persists its result, so every evaluation
                 * call runs inside a transaction that is always rolled back.
                 * The production database remains unchanged.
                 */
                DB::beginTransaction();

                try {
                    $result = $classifierService->classify(
                        $jobPosting->fresh()
                    );

                    $geminiMethod = (string) (
                        $result['method'] ?? ''
                    );

                    $geminiStatus = (string) (
                        $result['status']
                        ?? 'insufficient_evidence'
                    );

                    $geminiCareerPath = (string) (
                        $result['career_path_name'] ?? ''
                    );

                    $geminiPredictedLabel =
                        $this->resolvePredictedLabel(
                            $geminiStatus,
                            $geminiCareerPath
                        );
                } finally {
                    if (DB::transactionLevel() > 0) {
                        DB::rollBack();
                    }
                }

                if (
                    $geminiMethod !==
                    GeminiMarketJobCareerPathClassifierService::METHOD
                ) {
                    $fallbackCount++;
                }

                $isCorrect =
                    $geminiPredictedLabel === $expectedLabel;

                $geminiIsCorrect =
                    $isCorrect ? '1' : '0';

                $evaluatedRows[] = [
                    'expected' => $expectedLabel,
                    'predicted' => $geminiPredictedLabel,
                    'method' => $geminiMethod,
                ];
            } catch (Throwable $exception) {
                $failed++;
                $geminiError = $exception->getMessage();

                report($exception);
            }

            $outputRows[] = array_merge(
                $row,
                [
                    'gemini_predicted_label' => $geminiPredictedLabel,

                    'gemini_method' => $geminiMethod,

                    'gemini_status' => $geminiStatus,

                    'gemini_career_path' => $geminiCareerPath,

                    'gemini_is_correct' => $geminiIsCorrect,

                    'gemini_error' => $geminiError,
                ]
            );

            if ($delayMilliseconds > 0) {
                usleep(
                    $delayMilliseconds * 1000
                );
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        if ($evaluatedRows === []) {
            $this->error(
                'No evaluation rows were completed successfully.'
            );

            return self::FAILURE;
        }

        $outputPath = dirname($inputPath)
            .DIRECTORY_SEPARATOR
            .'classifier_evaluation_gemini_results.csv';

        $this->writeCsv(
            $outputPath,
            $outputRows
        );

        $overallMetrics = $this->calculateMetrics(
            $evaluatedRows
        );

        $directGeminiRows = array_values(
            array_filter(
                $evaluatedRows,
                fn (array $row): bool => $row['method'] ===
                    GeminiMarketJobCareerPathClassifierService::METHOD
            )
        );

        $directGeminiMetrics =
            $directGeminiRows !== []
                ? $this->calculateMetrics(
                    $directGeminiRows
                )
                : null;

        $this->table(
            ['Metric', 'Result'],
            [
                [
                    'Processed successfully',
                    (string) count($evaluatedRows),
                ],
                [
                    'Failed rows',
                    (string) $failed,
                ],
                [
                    'Gemini direct results',
                    (string) count($directGeminiRows),
                ],
                [
                    'Rules fallback results',
                    (string) $fallbackCount,
                ],
                [
                    'End-to-end correct decisions',
                    sprintf(
                        '%d / %d',
                        $overallMetrics['correct'],
                        $overallMetrics['total']
                    ),
                ],
                [
                    'End-to-end Accuracy',
                    $this->percentage(
                        $overallMetrics['accuracy']
                    ),
                ],
                [
                    'End-to-end Macro Precision',
                    $this->percentage(
                        $overallMetrics['macro_precision']
                    ),
                ],
                [
                    'End-to-end Macro Recall',
                    $this->percentage(
                        $overallMetrics['macro_recall']
                    ),
                ],
                [
                    'End-to-end Macro F1',
                    $this->percentage(
                        $overallMetrics['macro_f1']
                    ),
                ],
                [
                    'End-to-end Weighted F1',
                    $this->percentage(
                        $overallMetrics['weighted_f1']
                    ),
                ],
                [
                    'Gemini-only Accuracy',
                    $directGeminiMetrics !== null
                        ? $this->percentage(
                            $directGeminiMetrics['accuracy']
                        )
                        : 'N/A',
                ],
                [
                    'Gemini-only Macro F1',
                    $directGeminiMetrics !== null
                        ? $this->percentage(
                            $directGeminiMetrics['macro_f1']
                        )
                        : 'N/A',
                ],
                [
                    'Results CSV',
                    $outputPath,
                ],
            ]
        );

        $perLabelRows = [];

        foreach (
            $overallMetrics['per_label'] as $label => $metrics
        ) {
            $perLabelRows[] = [
                $label,
                $this->percentage(
                    $metrics['precision']
                ),
                $this->percentage(
                    $metrics['recall']
                ),
                $this->percentage(
                    $metrics['f1']
                ),
                (string) $metrics['support'],
            ];
        }

        $this->table(
            [
                'Label',
                'Precision',
                'Recall',
                'F1',
                'Support',
            ],
            $perLabelRows
        );

        return $failed > 0
            ? self::FAILURE
            : self::SUCCESS;
    }

    private function resolveInputPath(
        string $configuredPath
    ): string {
        if ($configuredPath === '') {
            throw new RuntimeException(
                'Evaluation file path is required.'
            );
        }

        if (File::exists($configuredPath)) {
            return realpath($configuredPath)
                ?: $configuredPath;
        }

        $projectPath = base_path(
            $configuredPath
        );

        if (File::exists($projectPath)) {
            return realpath($projectPath)
                ?: $projectPath;
        }

        throw new RuntimeException(
            'Evaluation CSV was not found: '
            .$configuredPath
        );
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function readCsv(
        string $path
    ): array {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException(
                'Unable to open evaluation CSV.'
            );
        }

        try {
            $headers = fgetcsv($handle);

            if (
                $headers === false ||
                $headers === []
            ) {
                throw new RuntimeException(
                    'Evaluation CSV header is missing.'
                );
            }

            $headers[0] = str_replace(
                "\xEF\xBB\xBF",
                '',
                (string) $headers[0]
            );

            $rows = [];

            while (
                ($values = fgetcsv($handle))
                !== false
            ) {
                if (
                    $values === [] ||
                    (
                        count($values) === 1 &&
                        trim((string) $values[0]) === ''
                    )
                ) {
                    continue;
                }

                $values = array_pad(
                    $values,
                    count($headers),
                    ''
                );

                $row = array_combine(
                    $headers,
                    array_slice(
                        $values,
                        0,
                        count($headers)
                    )
                );

                if ($row !== false) {
                    $rows[] = $row;
                }
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     */
    private function writeCsv(
        string $path,
        array $rows
    ): void {
        if ($rows === []) {
            return;
        }

        File::ensureDirectoryExists(
            dirname($path)
        );

        $handle = fopen($path, 'w');

        if ($handle === false) {
            throw new RuntimeException(
                'Unable to create Gemini evaluation results CSV.'
            );
        }

        try {
            fwrite(
                $handle,
                "\xEF\xBB\xBF"
            );

            $headers = array_keys(
                $rows[0]
            );

            fputcsv(
                $handle,
                $headers
            );

            foreach ($rows as $row) {
                fputcsv(
                    $handle,
                    array_map(
                        fn (string $header): string => (string) (
                            $row[$header] ?? ''
                        ),
                        $headers
                    )
                );
            }
        } finally {
            fclose($handle);
        }
    }

    private function resolvePredictedLabel(
        string $status,
        string $careerPathName
    ): string {
        if ($status !== 'classified') {
            return in_array(
                $status,
                self::LABELS,
                true
            )
                ? $status
                : 'insufficient_evidence';
        }

        return match ($careerPathName) {
            'Backend Developer' => 'backend',
            'Frontend Developer' => 'frontend',
            'Mobile Developer' => 'mobile',
            'DevOps Engineer' => 'devops',
            default => 'insufficient_evidence',
        };
    }

    /**
     * @param  array<int, array{expected: string, predicted: string, method: string}>  $rows
     * @return array<string, mixed>
     */
    private function calculateMetrics(
        array $rows
    ): array {
        $total = count($rows);
        $correct = 0;
        $perLabel = [];

        foreach ($rows as $row) {
            if (
                $row['expected'] ===
                $row['predicted']
            ) {
                $correct++;
            }
        }

        foreach (self::LABELS as $label) {
            $truePositive = 0;
            $falsePositive = 0;
            $falseNegative = 0;
            $support = 0;

            foreach ($rows as $row) {
                if ($row['expected'] === $label) {
                    $support++;
                }

                if (
                    $row['expected'] === $label &&
                    $row['predicted'] === $label
                ) {
                    $truePositive++;
                }

                if (
                    $row['expected'] !== $label &&
                    $row['predicted'] === $label
                ) {
                    $falsePositive++;
                }

                if (
                    $row['expected'] === $label &&
                    $row['predicted'] !== $label
                ) {
                    $falseNegative++;
                }
            }

            $precision = (
                $truePositive + $falsePositive
            ) > 0
                ? $truePositive /
                    (
                        $truePositive +
                        $falsePositive
                    )
                : 0.0;

            $recall = (
                $truePositive + $falseNegative
            ) > 0
                ? $truePositive /
                    (
                        $truePositive +
                        $falseNegative
                    )
                : 0.0;

            $f1 = (
                $precision + $recall
            ) > 0
                ? 2 *
                    $precision *
                    $recall /
                    (
                        $precision +
                        $recall
                    )
                : 0.0;

            $perLabel[$label] = [
                'precision' => $precision,
                'recall' => $recall,
                'f1' => $f1,
                'support' => $support,
            ];
        }

        $labelCount = count(
            self::LABELS
        );

        $macroPrecision =
            array_sum(
                array_column(
                    $perLabel,
                    'precision'
                )
            ) / $labelCount;

        $macroRecall =
            array_sum(
                array_column(
                    $perLabel,
                    'recall'
                )
            ) / $labelCount;

        $macroF1 =
            array_sum(
                array_column(
                    $perLabel,
                    'f1'
                )
            ) / $labelCount;

        $weightedF1 = 0.0;

        if ($total > 0) {
            foreach ($perLabel as $metrics) {
                $weightedF1 +=
                    $metrics['f1'] *
                    (
                        $metrics['support'] /
                        $total
                    );
            }
        }

        return [
            'total' => $total,
            'correct' => $correct,
            'accuracy' => $total > 0
                ? $correct / $total
                : 0.0,

            'macro_precision' => $macroPrecision,

            'macro_recall' => $macroRecall,

            'macro_f1' => $macroF1,

            'weighted_f1' => $weightedF1,

            'per_label' => $perLabel,
        ];
    }

    private function percentage(
        float $value
    ): string {
        return number_format(
            $value * 100,
            2
        ).'%';
    }
}
