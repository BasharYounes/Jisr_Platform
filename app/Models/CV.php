<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'UserId', 'id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class, 'cv_id', 'CvID');
    }

    public function analyses(): HasMany
    {
        return $this->hasMany(CVAnalysis::class, 'CvId', 'CvID');
    }

    public function latestAnalysis(): HasOne
    {
        return $this->hasOne(CVAnalysis::class, 'CvId', 'CvID')
            ->latestOfMany('CVAnalysisID');
    }
}
