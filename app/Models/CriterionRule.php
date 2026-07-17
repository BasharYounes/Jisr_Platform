<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CriterionRule extends Model
{
    protected $table = 'criterion_rules';

    protected $primaryKey = 'CriterionRuleID';

    protected $fillable = [
        'RuleSetID',
        'QuestionRubricID',
        'RuleCode',
        'RuleType',
        'Priority',
        'ConditionsJson',
        'AwardScore',
        'FeedbackTemplate',
        'IsActive',
    ];

    protected $casts = [
        'Priority' => 'integer',
        'ConditionsJson' => 'array',
        'AwardScore' => 'decimal:2',
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

    public function rubric()
    {
        return $this->belongsTo(
            QuestionRubric::class,
            'QuestionRubricID',
            'QuestionRubricID'
        );
    }
}
