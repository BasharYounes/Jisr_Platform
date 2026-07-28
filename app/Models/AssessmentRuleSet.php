<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentRuleSet extends Model
{
    protected $table = 'assessment_rule_sets';

    protected $primaryKey = 'RuleSetID';

    protected $fillable = [
        'QuestionID',
        'RuleSetCode',
        'Version',
        'Status',
        'CreatedByUserId',
        'ActivatedAt',
    ];

    protected $casts = [
        'ActivatedAt' => 'datetime',
    ];

    public function question()
    {
        return $this->belongsTo(
            QuestionBank::class,
            'QuestionID',
            'QuestionID'
        );
    }

    public function createdBy()
    {
        return $this->belongsTo(
            User::class,
            'CreatedByUserId',
            'id'
        );
    }

    public function criterionRules()
    {
        return $this->hasMany(
            CriterionRule::class,
            'RuleSetID',
            'RuleSetID'
        );
    }

    public function contradictionRules()
    {
        return $this->hasMany(
            AssessmentContradictionRule::class,
            'RuleSetID',
            'RuleSetID'
        );
    }

    public function evaluationRuns()
    {
        return $this->hasMany(
            AssessmentEvaluationRun::class,
            'RuleSetID',
            'RuleSetID'
        );
    }
}
