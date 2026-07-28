<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionBank extends Model
{
    protected $table = 'question_bank';

    protected $primaryKey = 'QuestionID';

    protected $fillable = [
        'SkillID',
        'CareerPathID',
        'Level',
        'QuestionType',
        'Topic',
        'QuestionText',
        'ExpectedAnswerType',
        'DifficultyWeight',
        'IsActive',
        'CreatedByUserId',
        'EvaluationEngine',
        'RuleSetVersion',
        'IsExpertReady',
    ];

    protected $casts = [
        'IsActive' => 'boolean',
        'DifficultyWeight' => 'decimal:2',
        'IsExpertReady' => 'boolean',
    ];

    public function skill()
    {
        return $this->belongsTo(Skill::class, 'SkillID', 'id');
    }

    public function careerPath()
    {
        return $this->belongsTo(CareerPath::class, 'CareerPathID', 'CareerPathID');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'CreatedByUserId', 'id');
    }

    public function rubrics()
    {
        return $this->hasMany(QuestionRubric::class, 'QuestionID', 'QuestionID');
    }

    public function attempts()
    {
        return $this->hasMany(AssessmentQuestionAttempt::class, 'QuestionID', 'QuestionID');
    }

    public function ruleSets()
    {
        return $this->hasMany(
            AssessmentRuleSet::class,
            'QuestionID',
            'QuestionID'
        );
    }
}
