<?php

namespace App\Console\Commands;

use App\Models\AssessmentEvaluationRun;
use App\Models\AssessmentQuestionAttempt;
use App\Models\AssessmentSession;
use App\Models\AssessmentSkillSession;
use App\Models\CareerPath;
use App\Models\QuestionBank;
use App\Models\Skill;
use App\Models\User;
use App\Services\Assessment\AssessmentExpertEvaluationOrchestratorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
// use LogicException;
use Throwable;

class VerifyExpertAuditTrail extends Command
{
    protected $signature = 'assessment:verify-expert-audit
                            {questionId=117 : QuestionBank.QuestionID}
                            {--answer= : Student answer to evaluate}
                            {--user-id= : Optional existing UserID for temporary foreign-key linking}';

    protected $description = 'Verifies Gemini evidence, Expert Rule Engine, and audit persistence inside a rolled-back transaction.';

    private const DEFAULT_ANSWER =
        'المتغير اسم يشير إلى قيمة. '
        .'القيمة هي البيانات. '
        .'في x = 5 يكون x متغيرًا و5 قيمة. '
        .'x = 5';

    public function __construct(
        private readonly AssessmentExpertEvaluationOrchestratorService $orchestrator,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $questionId = (int) $this->argument('questionId');

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

        $skillId = (int) ($question->SkillID ?? 0);

        if ($skillId <= 0 || ! Skill::query()->find($skillId)) {
            $this->error(
                "QuestionID {$questionId} does not have a valid SkillID."
            );

            return self::FAILURE;
        }

        $userId = $this->resolveUserId();

        if ($userId === null) {
            $this->error(
                'No User record exists. A temporary test session requires a valid UserID.'
            );

            return self::FAILURE;
        }

        $careerPath = $this->resolveCareerPath($question);

        if (! $careerPath) {
            $this->error(
                'No valid CareerPath record exists for the temporary test session.'
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

        $initialLevel = max(
            1.0,
            min(5.0, (float) ($question->Level ?? 1))
        );

        $this->newLine();
        $this->info('Starting temporary audit-trail verification...');
        $this->line("QuestionID: {$question->QuestionID}");
        $this->line("SkillID: {$skillId}");
        $this->line("Temporary UserID: {$userId}");
        $this->line(
            "Temporary CareerPathID: {$careerPath->CareerPathID}"
        );

        $this->newLine();
        $this->line('Student answer:');
        $this->line($studentAnswer);

        DB::beginTransaction();

        try {
            /*
             * Temporary parent records.
             * They exist only inside this database transaction.
             */
            $session = AssessmentSession::query()->create([
                'UserID' => $userId,
                'CvID' => null,
                'CareerPathID' => $careerPath->CareerPathID,
                'Status' => AssessmentSession::STATUS_IN_PROGRESS,
                'InitialSkillsSnapshotJson' => [],
                'StartedAt' => now(),
            ]);

            $skillSession = AssessmentSkillSession::query()->create([
                'AssessmentSessionID' => $session->AssessmentSessionID,
                'SkillID' => $skillId,
                'InitialLevel' => $initialLevel,
                'CurrentEstimatedLevel' => $initialLevel,
                'QuestionCount' => 0,
                'Status' => AssessmentSkillSession::STATUS_IN_PROGRESS,
            ]);

            $attempt = AssessmentQuestionAttempt::query()->create([
                'AssessmentSkillSessionID' => (
                    $skillSession->AssessmentSkillSessionID
                ),
                'QuestionID' => $question->QuestionID,
                'QuestionLevel' => $question->Level,
                'AskedAt' => now(),
                'LlmEvaluationStatus' => 'pending',
                'EvaluationEngine' => 'expert_rules',
                'EvaluationStatus' => 'pending',
                'EvaluationEngineVersion' => $question->RuleSetVersion,
            ]);

            /*
             * This calls:
             * GeminiEvidenceExtractionService
             * ↓
             * ExpertRuleEngineService
             * ↓
             * AssessmentEvaluationRun + AssessmentEvaluationEvidence
             */
            $result = $this->orchestrator->evaluateAndPersist(
                attempt: $attempt,
                studentAnswer: $studentAnswer,
            );

            $evaluationRun = AssessmentEvaluationRun::query()
                ->with([
                    'ruleSet',
                    'evidence.concept',
                ])
                ->findOrFail($result['evaluation_run_id']);

            $this->newLine();
            $this->info(
                'Saved Evaluation Run inside the temporary transaction:'
            );

            $this->table(
                [
                    'Field',
                    'Value',
                ],
                [
                    ['EvaluationRunID', $evaluationRun->EvaluationRunID],
                    ['AttemptID', $evaluationRun->AssessmentQuestionAttemptID],
                    ['Status', $evaluationRun->Status],
                    ['Engine', $evaluationRun->Engine],
                    ['Engine Version', $evaluationRun->EngineVersion],
                    ['Rule Set', $evaluationRun->ruleSet?->RuleSetCode ?? '-'],
                    ['Total Score', $evaluationRun->TotalScore],
                    ['Normalized Score', $evaluationRun->NormalizedScore],
                    ['Evidence Count', $evaluationRun->evidence->count()],
                    [
                        'Pipeline Version',
                        data_get(
                            $evaluationRun->EvaluationJson,
                            'pipeline_version',
                            '-'
                        ),
                    ],
                ]
            );

            $evidenceRows = $evaluationRun->evidence
                ->map(function ($evidence): array {
                    return [
                        $evidence->EvidenceID,
                        $evidence->concept?->ConceptCode ?? '-',
                        $evidence->EvidenceText,
                        $evidence->DetectionMethod,
                        $evidence->IsContradiction ? 'yes' : 'no',
                    ];
                })
                ->all();

            $this->newLine();
            $this->info('Saved evidence rows:');

            $this->table(
                [
                    'Evidence ID',
                    'Concept',
                    'Exact Evidence',
                    'Detection Method',
                    'Contradiction',
                ],
                $evidenceRows
            );

            $this->newLine();
            $this->info(
                'Audit trail verification succeeded inside the transaction.'
            );

            $this->comment(
                'All temporary rows will now be rolled back automatically.'
            );

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error(
                'Audit-trail verification failed: '
                .$exception->getMessage()
            );

            return self::FAILURE;
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }
    }

    private function resolveUserId(): ?int
    {
        $optionUserId = $this->option('user-id');

        if ($optionUserId !== null && $optionUserId !== '') {
            $userId = (int) $optionUserId;

            return User::query()
                ->whereKey($userId)
                ->exists()
                ? $userId
                : null;
        }

        $firstUserId = User::query()
            ->orderBy('id')
            ->value('id');

        return $firstUserId !== null
            ? (int) $firstUserId
            : null;
    }

    private function resolveCareerPath(
        QuestionBank $question,
    ): ?CareerPath {
        $questionCareerPathId = (int) (
            $question->CareerPathID ?? 0
        );

        if ($questionCareerPathId > 0) {
            $careerPath = CareerPath::query()
                ->find($questionCareerPathId);

            if ($careerPath) {
                return $careerPath;
            }
        }

        return CareerPath::query()
            ->orderBy('CareerPathID')
            ->first();
    }
}
