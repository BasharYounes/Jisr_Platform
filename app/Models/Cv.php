<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CV extends Model
{
    protected $table = 'c_v_s';

    protected $primaryKey = 'CvID';

    protected $fillable = [
        'UserId',
        'FileUrl',
        'IsPrimary',
        'UploadedAt',
    ];

    protected $casts = [
        'IsPrimary' => 'boolean',
        'UploadedAt' => 'datetime',
    ];

    public $timestamps = false;

    // public function analysis(): HasOne
    // {
    //     return $this->hasOne(CVAnalysis::class, 'CvId', 'CvID');
    // }

    public function assessmentSessions(): HasMany
    {
        return $this->hasMany(AssessmentSession::class, 'CvID', 'CvID');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'UserId', 'id');
    }

    public function getRouteKeyName(): string
    {
        return 'CvID';
    }
}
