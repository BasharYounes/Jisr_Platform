<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'assessment_session_id',
        'assessment_skill_session_id',
        'assessment_question_attempt_id',
        'question_id',
        'event_type',
        'level_before',
        'level_after',
        'normalized_score',
        'confidence_score',
        'payload',
        'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'level_before' => 'decimal:2',
        'level_after' => 'decimal:2',
        'normalized_score' => 'decimal:4',
        'confidence_score' => 'decimal:4',
        'created_at' => 'datetime',
    ];
}
