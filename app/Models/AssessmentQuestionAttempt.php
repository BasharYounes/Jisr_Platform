<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentQuestionAttempt extends Model
{
    protected $table = 'assessment_question_attempts';

    protected $primaryKey = 'AssessmentQuestionAttemptID';

    protected $fillable = [
        'AssessmentSkillSessionID',
        'QuestionID',
        'QuestionLevel',
        'AskedAt',
        'AnsweredAt',
        'LlmEvaluationStatus',
        'RawScore',
        'NormalizedScore',
        'FeedbackText',
        'EvaluationJson',
        'EvaluationEngine',
        'EvaluationStatus',
        'EvaluationEngineVersion',
    ];

    protected $casts = [
        'QuestionLevel' => 'integer',
        'AskedAt' => 'datetime',
        'AnsweredAt' => 'datetime',
        'RawScore' => 'decimal:2',
        'NormalizedScore' => 'decimal:2',
        'EvaluationJson' => 'array',
    ];

    public function assessmentSkillSession()
    {
        return $this->belongsTo(AssessmentSkillSession::class, 'AssessmentSkillSessionID', 'AssessmentSkillSessionID');
    }

    public function questionBank()
    {
        return $this->belongsTo(QuestionBank::class, 'QuestionID', 'QuestionID');
    }

    public function answer()
    {
        return $this->hasOne(AssessmentAnswer::class, 'AssessmentQuestionAttemptID', 'AssessmentQuestionAttemptID');
    }

    public function getRouteKeyName(): string
    {
        return 'AssessmentQuestionAttemptID';
    }

    public function evaluationRuns()
    {
        return $this->hasMany(
            AssessmentEvaluationRun::class,
            'AssessmentQuestionAttemptID',
            'AssessmentQuestionAttemptID'
        );
    }
}


/*

$result = $engine->evaluate(\App\Models\QuestionBank::findOrFail(117),[['concept_code' => 'variable_is_identifier_or_reference','value' => true,'is_negated' => true,'evidence' => 'المتغير لا يشير إلى قيمة.','sentence_index' => 0,'detection_method' => 'manual_test','similarity_score' => null,],['concept_code' => 'valid_python_assignment_example','value' => true,'is_negated' => false,'evidence' => 'x = 5','sentence_index' => 1,'detection_method' => 'manual_test','similarity_score' => null,],]);

collect($result)->only(['total_score','max_score','normalized_score','criteria_results','contradictions',]);

*/
