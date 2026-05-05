<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentSession extends Model
{
    protected $table = 'assessment_sessions';
    protected $primaryKey = 'AssessmentSessionID';

    protected $fillable = [
        'UserID',
        'CvID',
        'CareerPathID',
        'Status',
        'InitialSkillsSnapshotJson',
        'FinalResultsJson',
        'StartedAt',
        'CompletedAt',
    ];

    protected $casts = [
        'InitialSkillsSnapshotJson' => 'array',
        'FinalResultsJson' => 'array',
        'StartedAt' => 'datetime',
        'CompletedAt' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'UserID', 'id');
    }

    public function careerPath()
    {
        return $this->belongsTo(CareerPath::class, 'CareerPathID', 'CareerPathID');
    }

    public function skillSessions()
    {
        return $this->hasMany(AssessmentSkillSession::class, 'AssessmentSessionID', 'AssessmentSessionID');
    }

    public function cv()
    {
        return $this->belongsTo(CV::class, 'CvID', 'CvID');
    }

    public function getRouteKeyName(): string
    {
        return 'AssessmentSessionID';
    }

}
