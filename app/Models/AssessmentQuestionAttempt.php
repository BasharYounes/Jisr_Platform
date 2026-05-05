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
}
