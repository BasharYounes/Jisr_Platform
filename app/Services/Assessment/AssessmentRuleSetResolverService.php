<?php

namespace App\Services\Assessment;

use App\Models\AssessmentRuleSet;
use App\Models\QuestionBank;
use LogicException;

class AssessmentRuleSetResolverService
{
    public function resolveActiveForQuestion(
        QuestionBank $question,
        array $relations = []
    ): AssessmentRuleSet {
        $query = AssessmentRuleSet::query()
            ->where('QuestionID', $question->QuestionID)
            ->where('Status', 'active');

        $configuredVersion = trim(
            (string) ($question->RuleSetVersion ?? '')
        );

        /*
         * If the question is pinned to a version,
         * never silently use another active version.
         */
        if ($configuredVersion !== '') {
            $query->where('Version', $configuredVersion);
        }

        if (! empty($relations)) {
            $query->with($relations);
        }

        $ruleSet = $query
            ->orderByDesc('ActivatedAt')
            ->orderByDesc('RuleSetID')
            ->first();

        if ($ruleSet) {
            return $ruleSet;
        }

        $versionText = $configuredVersion !== ''
            ? " with version {$configuredVersion}"
            : '';

        throw new LogicException(
            "No active Expert System rule set exists for "
            . "QuestionID {$question->QuestionID}{$versionText}."
        );
    }
}
