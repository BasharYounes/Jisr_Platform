<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentContradictionRule extends Model
{
    protected $table = 'assessment_contradiction_rules';

    protected $primaryKey = 'ContradictionRuleID';

    protected $fillable = [
        'RuleSetID',
        'TriggerConceptID',
        'Code',
        'Severity',
        'FeedbackAr',
        'IsActive',
    ];

    protected $casts = [
        'IsActive' => 'boolean',
    ];

    public function ruleSet()
    {
        return $this->belongsTo(
            AssessmentRuleSet::class,
            'RuleSetID',
            'RuleSetID'
        );
    }

    public function triggerConcept()
    {
        return $this->belongsTo(
            AssessmentConcept::class,
            'TriggerConceptID',
            'ConceptID'
        );
    }

    public function blockedRubrics()
    {
        return $this->belongsToMany(
            QuestionRubric::class,
            'assessment_contradiction_rule_rubrics',
            'ContradictionRuleID',
            'QuestionRubricID',
            'ContradictionRuleID',
            'QuestionRubricID'
        );
    }
}
