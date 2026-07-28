<?php

namespace App\Services\Assessment;

use App\Models\AssessmentConcept;
use App\Models\AssessmentEvaluationEvidence;
use App\Models\AssessmentEvaluationRun;
use App\Models\AssessmentQuestionAttempt;
use App\Models\AssessmentRuleSet;
use Illuminate\Support\Facades\DB;
use LogicException;
use Throwable;

class AssessmentExpertEvaluationOrchestratorService
{
    private const PIPELINE_VERSION = 'expert_gemini_evidence_v1';

    public function __construct(
        private readonly GeminiEvidenceExtractionService $evidenceService,
        private readonly ExpertRuleEngineService $expertRuleEngine,
    ) {}

    /**
     * Executes the official expert-evaluation pipeline and saves
     * an immutable audit trail.
     *
     * This method does NOT:
     * - create AssessmentAnswer
     * - update the attempt score/status
     * - update student level
     * - select the next question
     * - write telemetry
     *
     * Those responsibilities remain for the controller integration phase.
     */
    public function evaluateAndPersist(
        AssessmentQuestionAttempt $attempt,
        string $studentAnswer,
    ): array {
        $studentAnswer = trim($studentAnswer);

        if ($studentAnswer === '') {
            throw new LogicException(
                'Student answer cannot be empty.'
            );
        }

        $attempt->loadMissing('questionBank');

        if (! $attempt->questionBank) {
            throw new LogicException(
                'Assessment attempt does not have a question.'
            );
        }

        $question = $attempt->questionBank;

        if ($question->EvaluationEngine !== 'expert_rules') {
            throw new LogicException(
                'This question does not use expert_rules.'
            );
        }

        $ruleSet = $this->resolveActiveRuleSet($question);

        /*
         * We create the run before calling Gemini.
         * If Gemini fails, the database will still preserve a failed audit row.
         */
        $evaluationRun = AssessmentEvaluationRun::query()->create([
            'AssessmentQuestionAttemptID' => (
                $attempt->AssessmentQuestionAttemptID
            ),
            'RuleSetID' => $ruleSet->RuleSetID,
            'Engine' => 'expert_rules',
            'EngineVersion' => $ruleSet->Version,
            'Status' => 'pending',
            'RequestedAt' => now(),
        ]);

        try {
            /*
             * Gemini returns facts + exact evidence only.
             * No score, level, next question, or feedback is accepted.
             */
            $evidenceResult = $this->evidenceService->extract(
                question: $question,
                studentAnswer: $studentAnswer,
            );

            $facts = $evidenceResult['facts'] ?? [];

            /*
             * Laravel Expert Rule Engine is the only scoring authority.
             */
            $evaluation = $this->expertRuleEngine->evaluate(
                question: $question,
                facts: $facts,
            );

            DB::transaction(function () use (
                $evaluationRun,
                $facts,
                $evaluation,
            ): void {
                $this->persistEvidence(
                    evaluationRun: $evaluationRun,
                    facts: $facts,
                    evaluation: $evaluation,
                );

                $evaluationRun->update([
                    'Status' => 'completed',
                    'DetectedLanguage' => null,
                    'TotalScore' => (float) (
                        $evaluation['total_score'] ?? 0
                    ), (
                        $evaluation['total_score'] ?? 0
                    ),
                    'NormalizedScore' => (float) (
                        $evaluation['normalized_score'] ?? 0
                    ), (
                        $evaluation['normalized_score'] ?? 0
                    ),
                    'FeedbackAr' => (
                        $evaluation['feedback_ar'] ?? null
                    ),
                    'EvaluationJson' => [
                        'pipeline_version' => self::PIPELINE_VERSION,
                        'evidence_engine' => 'gemini',
                        'evidence_contract' => 'facts_only_v1',
                        'facts' => $facts,
                        'expert_evaluation' => $evaluation,
                    ],
                    'CompletedAt' => now(),
                ]);
            });

            return [
                'evaluation_run_id' => (
                    $evaluationRun->EvaluationRunID
                ),
                'facts' => $facts,
                'evaluation' => $evaluation,
            ];
        } catch (Throwable $exception) {
            $this->markRunAsFailed(
                evaluationRun: $evaluationRun,
                exception: $exception,
            );

            throw $exception;
        }
    }

    private function resolveActiveRuleSet(
        object $question,
    ): AssessmentRuleSet {
        $version = trim(
            (string) ($question->RuleSetVersion ?? '')
        );

        if ($version === '') {
            throw new LogicException(
                'Expert question does not have a RuleSetVersion.'
            );
        }

        $ruleSet = AssessmentRuleSet::query()
            ->where('QuestionID', $question->QuestionID)
            ->where('Version', $version)
            ->where('Status', 'active')
            ->first();

        if (! $ruleSet) {
            throw new LogicException(
                'No active Rule Set was found for question '
                ."{$question->QuestionID} version {$version}."
            );
        }

        return $ruleSet;
    }

    private function persistEvidence(
        AssessmentEvaluationRun $evaluationRun,
        array $facts,
        array $evaluation,
    ): void {
        if (empty($facts)) {
            return;
        }

        $conceptCodes = collect($facts)
            ->pluck('concept_code')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $conceptIdsByCode = AssessmentConcept::query()
            ->whereIn('ConceptCode', $conceptCodes)
            ->pluck('ConceptID', 'ConceptCode')
            ->all();

        $blockingConcepts = collect(
            $evaluation['contradictions'] ?? []
        )
            ->pluck('trigger_concept')
            ->filter()
            ->flip()
            ->all();

        foreach ($facts as $fact) {
            $conceptCode = $fact['concept_code'] ?? null;

            if (
                ! is_string($conceptCode)
                || ! isset($conceptIdsByCode[$conceptCode])
            ) {
                throw new LogicException(
                    'Cannot persist evidence for unknown concept: '
                    .(string) $conceptCode
                );
            }

            $language = $fact['language'] ?? null;

            if (
                ! in_array(
                    $language,
                    ['ar', 'en', 'mixed'],
                    true
                )
            ) {
                $language = null;
            }

            AssessmentEvaluationEvidence::query()->create([
                'EvaluationRunID' => (
                    $evaluationRun->EvaluationRunID
                ),
                'ConceptID' => $conceptIdsByCode[$conceptCode],

                /*
                 * A fact may contribute to multiple criteria.
                 * Keeping this null is more accurate than falsely
                 * assigning it to only one rubric.
                 */
                'QuestionRubricID' => null,

                'EvidenceText' => (string) (
                    $fact['evidence'] ?? ''
                ), (
                    $fact['evidence'] ?? ''
                ),
                'SentenceIndex' => (
                    $fact['sentence_index'] ?? null
                ),
                'Language' => $language,
                'DetectionMethod' => (string) (
                    $fact['detection_method']
                    ?? 'gemini_evidence'
                ), (
                    $fact['detection_method']
                    ?? 'gemini_evidence'
                ),
                'SimilarityScore' => (
                    $fact['similarity_score'] ?? null
                ),
                'IsNegated' => (bool) (
                    $fact['is_negated'] ?? false
                ), (
                    $fact['is_negated'] ?? false
                ),
                'IsContradiction' => isset(
                    $blockingConcepts[$conceptCode]
                ),
                'MetadataJson' => [
                    'pipeline_version' => self::PIPELINE_VERSION,
                    'provider' => 'gemini',
                    'fact_metadata' => (
                        $fact['metadata'] ?? []
                    ),
                ],
            ]);
        }
    }

    private function markRunAsFailed(
        AssessmentEvaluationRun $evaluationRun,
        Throwable $exception,
    ): void {
        try {
            $evaluationRun->update([
                'Status' => 'failed',
                'EvaluationJson' => [
                    'pipeline_version' => self::PIPELINE_VERSION,
                    'failure' => [
                        'exception_class' => get_class($exception),
                        'message' => $exception->getMessage(),
                    ],
                ],
                'CompletedAt' => now(),
            ]);
        } catch (Throwable) {
            /*
             * Preserve the original evaluation exception.
             * A failure while recording the failure must not hide it.
             */
        }
    }
}
