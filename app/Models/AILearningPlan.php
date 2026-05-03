<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AILearningPlan extends Model
{
    protected $table = 'a_i_learning_plans';
    protected $primaryKey = 'AILearningPlanID';

    protected $fillable = [
        'AssessmentSessionID',
        'UserID',
        'Status',
        'Weeks',
        'HoursPerWeek',
        'InputSnapshotJson',
        'PlanJson',
        'SummaryText',
        'AiModelVersion',
        'GeneratedAt',
    ];

    protected $casts = [
        'InputSnapshotJson' => 'array',
        'PlanJson' => 'array',
        'GeneratedAt' => 'datetime',
    ];

    public function assessmentSession()
    {
        return $this->belongsTo(AssessmentSession::class, 'AssessmentSessionID', 'AssessmentSessionID');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'UserID', 'id');
    }
}
