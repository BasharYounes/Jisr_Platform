<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentAnswer extends Model
{
    protected $table = 'assessment_answers';

    protected $primaryKey = 'AssessmentAnswerID';

    protected $fillable = [
        'AssessmentQuestionAttemptID',
        'AnswerText',
        'AnswerJson',
        'SubmittedAt',
    ];

    protected $casts = [
        'AnswerJson' => 'array',
        'SubmittedAt' => 'datetime',
    ];

    public function assessmentQuestionAttempt()
    {
        return $this->belongsTo(AssessmentQuestionAttempt::class, 'AssessmentQuestionAttemptID', 'AssessmentQuestionAttemptID');
    }
}
