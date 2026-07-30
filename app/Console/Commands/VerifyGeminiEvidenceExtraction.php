<?php

namespace App\Console\Commands;

use App\Models\QuestionBank;
use App\Services\AI\AIClientInterface;
use App\Services\AI\GeminiClient;
use App\Services\Assessment\ExpertRuleEngineService;
use App\Services\Assessment\GeminiEvidenceExtractionService;
use Illuminate\Console\Command;
use Throwable;

class VerifyGeminiEvidenceExtraction extends Command
{
    protected $signature = 'assessment:verify-gemini-evidence
                            {questionId=117 : QuestionBank.QuestionID}
                            {--answer= : Student answer to analyze}';

    protected $description = 'Verifies live Gemini evidence extraction and Laravel expert scoring without saving assessment data.';

    private const DEFAULT_ANSWER =
        'المتغير اسم يشير إلى قيمة. '
        .'القيمة هي البيانات. '
        .'في x = 5 يكون x متغيرًا و5 قيمة. '
        .'x = 5';

    public function __construct(
        private readonly GeminiEvidenceExtractionService $evidenceService,
        private readonly ExpertRuleEngineService $expertRuleEngine,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $questionId = (int) $this->argument('questionId');

        if ($questionId <= 0) {
            $this->error('questionId must be a positive integer.');

            return self::FAILURE;
        }

        $question = QuestionBank::query()->find($questionId);

        if (! $question) {
            $this->error("QuestionID {$questionId} was not found.");

            return self::FAILURE;
        }

        if ($question->EvaluationEngine !== 'expert_rules') {
            $this->error(
                "QuestionID {$questionId} does not use expert_rules."
            );

            return self::FAILURE;
        }

        $aiClient = app(AIClientInterface::class);

        $this->newLine();
        $this->info('Starting live Gemini evidence extraction...');
        $this->line("QuestionID: {$question->QuestionID}");
        $this->line("RuleSetVersion: {$question->RuleSetVersion}");
        $this->line('Resolved AI client: '.get_class($aiClient));

        if (! $aiClient instanceof GeminiClient) {
            $this->error(
                'Gemini is not active. '
                .'Set AI_PROVIDER=gemini in .env before running this command.'
            );

            return self::FAILURE;
        }

        $studentAnswer = trim(
            (string) (
                $this->option('answer')
                ?: self::DEFAULT_ANSWER
            )
        );

        if ($studentAnswer === '') {
            $this->error('Student answer cannot be empty.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->line('Student answer:');
        $this->line($studentAnswer);
        $this->newLine();

        try {
            /*
             * Gemini:
             * يفهم النص ويعيد facts مع evidence فقط.
             */
            $evidenceResult = $this->evidenceService->extract(
                question: $question,
                studentAnswer: $studentAnswer,
            );

            $facts = $evidenceResult['facts'] ?? [];

            /*
             * Laravel:
             * يطبّق Rules ويحسب الدرجة النهائية.
             */
            $evaluation = $this->expertRuleEngine->evaluate(
                question: $question,
                facts: $facts,
            );
        } catch (Throwable $exception) {
            $this->error(
                'Gemini-to-Expert-System verification failed: '
                .$exception->getMessage()
            );

            return self::FAILURE;
        }

        $this->info('Validated facts returned by Gemini:');

        if (empty($facts)) {
            $this->warn('Gemini returned no supported concepts.');
        } else {
            $rows = collect($facts)
                ->map(function (array $fact): array {
                    return [
                        $fact['concept_code'],
                        $fact['evidence'],
                        $fact['metadata']['provider'] ?? '-',
                    ];
                })
                ->all();

            $this->table(
                [
                    'Concept',
                    'Exact Evidence',
                    'Provider',
                ],
                $rows
            );
        }

        $this->newLine();
        $this->info(
            'Deterministic Laravel Expert Rule Engine result:'
        );

        $this->line("Engine: {$evaluation['engine']}");
        $this->line("Rule set: {$evaluation['rule_set_code']}");
        $this->line(
            "Score: {$evaluation['total_score']} / "
            ."{$evaluation['max_score']}"
        );
        $this->line(
            "Normalized score: {$evaluation['normalized_score']}"
        );

        $this->newLine();

        $criteriaRows = collect($evaluation['criteria_results'])
            ->map(function (array $criterion): array {
                return [
                    $criterion['criterion_code'],
                    "{$criterion['score']} / {$criterion['max_score']}",
                    $criterion['status'],
                    $criterion['matched_rule_code'] ?? '-',
                ];
            })
            ->all();

        $this->table(
            [
                'Criterion',
                'Score',
                'Status',
                'Matched Rule',
            ],
            $criteriaRows
        );

        if (! empty($evaluation['contradictions'])) {
            $this->newLine();
            $this->warn('Blocking contradictions used by Laravel:');

            $contradictionRows = collect($evaluation['contradictions'])
                ->map(function (array $contradiction): array {
                    return [
                        $contradiction['code'],
                        $contradiction['severity'],
                        $contradiction['trigger_concept'],
                    ];
                })
                ->all();

            $this->table(
                [
                    'Code',
                    'Severity',
                    'Trigger Concept',
                ],
                $contradictionRows
            );
        }

        $this->newLine();
        $this->line(
            'Arabic feedback: '.$evaluation['feedback_ar']
        );

        $this->newLine();
        $this->info(
            'Gemini evidence extraction and Laravel scoring completed.'
        );

        $this->comment(
            'No answer, attempt, evaluation run, evidence record, '
            .'score, level, or telemetry event was saved.'
        );

        return self::SUCCESS;
    }
}
