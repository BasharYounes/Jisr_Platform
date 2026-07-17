<?php

namespace App\Services\Assessment;

use App\Models\AssessmentRuleSet;
use App\Models\CriterionRule;
use App\Models\QuestionBank;
use App\Models\QuestionRubric;
use Illuminate\Support\Collection;
use LogicException;


class ExpertRuleEngineService
{

    public function __construct(
        private readonly AssessmentRuleSetResolverService $ruleSetResolver
    ) {
    }
    /**
     * Evaluates one answer using already-extracted facts.
     *
     * Important:
     * - This service never calls Gemini or any LLM.
     * - This service never calculates semantic similarity.
     * - NLP will later provide facts/evidence only.
     * - The final score is decided here through deterministic rules.
     *
     * Expected fact format:
     *
     * [
     *     [
     *         'concept_code' => 'variable_is_identifier_or_reference',
     *         'value' => true,
     *         'is_negated' => false,
     *         'evidence' => 'المتغير اسم يشير إلى قيمة',
     *         'sentence_index' => 0,
     *         'detection_method' => 'alias',
     *         'similarity_score' => null,
     *     ],
     * ]
     */
    public function evaluate(QuestionBank $question, array $facts): array
    {
        $ruleSet = $this->ruleSetResolver
            ->resolveActiveForQuestion(
                question: $question,
                relations: [
                    'criterionRules' => function ($query) {
                        $query->where('IsActive', true);
                    },
                    'contradictionRules' => function ($query) {
                        $query
                            ->where('IsActive', true)
                            ->with([
                                'triggerConcept',
                                'blockedRubrics',
                            ]);
                    },
                ]
            );

        if (! $ruleSet) {
            throw new LogicException(
                "No active Expert System rule set exists for QuestionID {$question->QuestionID}."
            );
        }

        $rubrics = QuestionRubric::query()
            ->where('QuestionID', $question->QuestionID)
            ->orderBy('OrderIndex')
            ->get();

        if ($rubrics->isEmpty()) {
            throw new LogicException(
                "QuestionID {$question->QuestionID} has no rubrics to evaluate."
            );
        }

        $factIndex = $this->buildFactIndex($facts);

        $triggeredContradictions = $this->resolveTriggeredContradictions(
            ruleSet: $ruleSet,
            factIndex: $factIndex
        );

        $blockedRubricIds = $triggeredContradictions
            ->flatMap(
                fn ($contradiction) => $contradiction->blockedRubrics
                    ->pluck('QuestionRubricID')
            )
            ->unique()
            ->values()
            ->all();

        $rulesByRubric = $ruleSet->criterionRules
            ->filter(fn (CriterionRule $rule) => $rule->IsActive)
            ->sortBy('Priority')
            ->groupBy('QuestionRubricID');

        $criteriaResults = [];
        $totalScore = 0.0;
        $maxScore = 0.0;
        $feedbackMessages = [];

        $contradictionFeedbackMessages = $triggeredContradictions
            ->pluck('FeedbackAr')
            ->filter()
            ->unique()
            ->values()
            ->all();

        foreach ($rubrics as $rubric) {
            $rubricId = (int) $rubric->QuestionRubricID;
            $criterionMaxScore = (float) $rubric->MaxScore;

            $maxScore += $criterionMaxScore;

            $blockingContradictions = $triggeredContradictions
                ->filter(
                    fn ($contradiction) => $contradiction->blockedRubrics
                        ->contains(
                            'QuestionRubricID',
                            $rubricId
                        )
                )
                ->values();

            if (in_array($rubricId, $blockedRubricIds, true)) {
                $feedback = $blockingContradictions
                    ->pluck('FeedbackAr')
                    ->filter()
                    ->unique()
                    ->implode(' ');

                $criteriaResults[] = [
                    'criterion_code' => $rubric->CriterionCode,
                    'criterion_name' => $rubric->CriterionName,
                    'score' => 0.0,
                    'max_score' => $criterionMaxScore,
                    'status' => 'blocked_by_contradiction',
                    'matched_rule_code' => null,
                    'comment' => $feedback,
                ];

                continue;
            }

            $matchedRule = $this->findFirstMatchingRule(
                rules: $rulesByRubric->get($rubricId, collect()),
                factIndex: $factIndex
            );

            if ($matchedRule) {
                $awardedScore = min(
                    (float) $matchedRule->AwardScore,
                    $criterionMaxScore
                );

                $totalScore += $awardedScore;

                $criteriaResults[] = [
                    'criterion_code' => $rubric->CriterionCode,
                    'criterion_name' => $rubric->CriterionName,
                    'score' => $awardedScore,
                    'max_score' => $criterionMaxScore,
                    'status' => 'awarded',
                    'matched_rule_code' => $matchedRule->RuleCode,
                    'comment' => $matchedRule->FeedbackTemplate
                        ?: $rubric->FeedbackOnPass,
                ];

                continue;
            }

            $failureFeedback = $rubric->FeedbackOnFail
                ?: "لم يتحقق معيار: {$rubric->CriterionName}.";

            $criteriaResults[] = [
                'criterion_code' => $rubric->CriterionCode,
                'criterion_name' => $rubric->CriterionName,
                'score' => 0.0,
                'max_score' => $criterionMaxScore,
                'status' => 'not_met',
                'matched_rule_code' => null,
                'comment' => $failureFeedback,
            ];

            $feedbackMessages[] = $failureFeedback;
        }

        $normalizedScore = $maxScore > 0
            ? round($totalScore / $maxScore, 4)
            : 0.0;

        $feedbackMessages = array_values(
            array_unique(
                array_filter(
                    array_merge(
                        $contradictionFeedbackMessages,
                        $feedbackMessages
                    )
                )
            )
        );

        $feedbackAr = empty($feedbackMessages)
            ? 'إجابتك حققت جميع المعايير المطلوبة.'
            : implode("\n", array_slice($feedbackMessages, 0, 6));

        return [
            'engine' => 'expert_rules',
            'engine_version' => $ruleSet->Version,
            'rule_set_code' => $ruleSet->RuleSetCode,
            'rule_set_id' => $ruleSet->RuleSetID,

            'total_score' => round($totalScore, 2),
            'max_score' => round($maxScore, 2),
            'normalized_score' => $normalizedScore,

            'feedback_ar' => $feedbackAr,

            'criteria_results' => $criteriaResults,

            'contradictions' => $triggeredContradictions
                ->map(fn ($contradiction) => [
                    'code' => $contradiction->Code,
                    'severity' => $contradiction->Severity,
                    'feedback_ar' => $contradiction->FeedbackAr,
                    'trigger_concept' => $contradiction->triggerConcept?->ConceptCode,
                ])
                ->values()
                ->all(),

            'facts_received' => array_values($facts),
        ];
    }

    private function buildFactIndex(array $facts): array
    {
        $index = [];

        foreach ($facts as $fact) {
            $conceptCode = $fact['concept_code'] ?? null;

            if (! is_string($conceptCode) || $conceptCode === '') {
                continue;
            }

            $index[$conceptCode][] = [
                'value' => (bool) ($fact['value'] ?? false),
                'is_negated' => (bool) ($fact['is_negated'] ?? false),
                'evidence' => $fact['evidence'] ?? null,
                'sentence_index' => $fact['sentence_index'] ?? null,
                'detection_method' => $fact['detection_method'] ?? null,
                'similarity_score' => $fact['similarity_score'] ?? null,
            ];
        }

        return $index;
    }

    private function resolveTriggeredContradictions(
        AssessmentRuleSet $ruleSet,
        array $factIndex
    ): Collection {
        return  $ruleSet->contradictionRules
            ->filter(function ($contradiction) use ($factIndex) {
                if (! $contradiction->IsActive) {
                    return false;
                }
                $conceptCode = $contradiction->triggerConcept?->ConceptCode;

                if (! $conceptCode) {
                    return false;
                }

                return $this->conditionMatches(
                    [
                        'concept' => $conceptCode,
                        'expected' => true,
                        'not_negated' => true,
                    ],
                    $factIndex
                );
            })
            ->values();
    }

    private function findFirstMatchingRule(
        Collection $rules,
        array $factIndex
    ): ?CriterionRule {
        foreach ($rules as $rule) {
            if ($this->ruleMatches($rule, $factIndex)) {
                return $rule;
            }
        }

        return null;
    }

    private function ruleMatches(
        CriterionRule $rule,
        array $factIndex
    ): bool {
        $conditions = $rule->ConditionsJson ?? [];

        $allConditions = $conditions['all'] ?? [];
        $noneConditions = $conditions['none'] ?? [];

        foreach ($allConditions as $condition) {
            if (! $this->conditionMatches($condition, $factIndex)) {
                return false;
            }
        }

        foreach ($noneConditions as $condition) {
            if ($this->conditionMatches($condition, $factIndex)) {
                return false;
            }
        }

        return true;
    }

    private function conditionMatches(
        array $condition,
        array $factIndex
    ): bool {
        $conceptCode = $condition['concept'] ?? null;

        if (! is_string($conceptCode) || $conceptCode === '') {
            return false;
        }

        $expectedValue = (bool) ($condition['expected'] ?? true);

        /*
         * For positive concepts, a negated statement is not valid evidence.
         * Example:
         * "المتغير لا يشير إلى قيمة"
         * must not satisfy:
         * "المتغير يشير إلى قيمة".
         */
        $requiresNotNegated = array_key_exists(
            'not_negated',
            $condition
        )
            ? (bool) $condition['not_negated']
            : $expectedValue === true;

        foreach ($factIndex[$conceptCode] ?? [] as $fact) {
            if ($fact['value'] !== $expectedValue) {
                continue;
            }

            if ($requiresNotNegated && $fact['is_negated']) {
                continue;
            }

            return true;
        }

        return false;
    }
}
