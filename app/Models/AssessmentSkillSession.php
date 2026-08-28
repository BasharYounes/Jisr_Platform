<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentSkillSession extends Model
{
    protected $table = 'assessment_skill_sessions';

    protected $primaryKey = 'AssessmentSkillSessionID';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_NEEDS_REVIEW = 'needs_review';

    protected $fillable = [
        'AssessmentSessionID',
        'SkillID',
        'InitialLevel',
        'CurrentEstimatedLevel',
        'FinalLevel',
        'ConfidenceScore',
        'QuestionCount',
        'Status',
        'CompletedAt',
    ];

    protected $casts = [
        'InitialLevel' => 'decimal:1',
        'CurrentEstimatedLevel' => 'decimal:1',
        'FinalLevel' => 'decimal:1',
        'ConfidenceScore' => 'decimal:2',
        'QuestionCount' => 'integer',
        'CompletedAt' => 'datetime',
    ];

    public function assessmentSession()
    {
        return $this->belongsTo(AssessmentSession::class, 'AssessmentSessionID', 'AssessmentSessionID');
    }

    public function skill()
    {
        return $this->belongsTo(Skill::class, 'SkillID', 'id');
    }

    //
    public function questionAttempts()
    {
        return $this->hasMany(AssessmentQuestionAttempt::class, 'AssessmentSkillSessionID', 'AssessmentSkillSessionID');
    }

    public function attempts()
    {
        return $this->questionAttempts();
    }
}
