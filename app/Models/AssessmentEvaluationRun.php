<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentEvaluationRun extends Model
{
    protected $table = 'assessment_evaluation_runs';

    protected $primaryKey = 'EvaluationRunID';

    protected $fillable = [
        'AssessmentQuestionAttemptID',
        'RuleSetID',
        'Engine',
        'EngineVersion',
        'Status',
        'DetectedLanguage',
        'TotalScore',
        'NormalizedScore',
        'FeedbackAr',
        'EvaluationJson',
        'RequestedAt',
        'CompletedAt',
    ];

    protected $casts = [
        'TotalScore' => 'decimal:2',
        'NormalizedScore' => 'decimal:4',
        'EvaluationJson' => 'array',
        'RequestedAt' => 'datetime',
        'CompletedAt' => 'datetime',
    ];

    public function attempt()
    {
        return $this->belongsTo(
            AssessmentQuestionAttempt::class,
            'AssessmentQuestionAttemptID',
            'AssessmentQuestionAttemptID'
        );
    }

    public function ruleSet()
    {
        return $this->belongsTo(
            AssessmentRuleSet::class,
            'RuleSetID',
            'RuleSetID'
        );
    }

    public function evidence()
    {
        return $this->hasMany(
            AssessmentEvaluationEvidence::class,
            'EvaluationRunID',
            'EvaluationRunID'
        );
    }
}
