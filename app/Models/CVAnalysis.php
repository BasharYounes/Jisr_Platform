<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CVAnalysis extends Model
{
    protected $table = 'c_v_analyses';

    protected $primaryKey = 'CVAnalysisID';

    protected $fillable = [
        'CvId',
        'ExtractedSkillsJson',
        'MissingCriteriaJson',
        'OverallScore',
        'AiModelVersion',
        'AnalyzedAt',
    ];

    protected $casts = [
        'ExtractedSkillsJson' => 'array',
        'MissingCriteriaJson' => 'array',
        'OverallScore' => 'decimal:2',
        'AnalyzedAt' => 'datetime',
    ];

    public function cv(): BelongsTo
    {
        return $this->belongsTo(CV::class, 'CvId', 'CvID');
    }

    public function extractedSkills(): HasMany
    {
        return $this->hasMany(CVExtractedSkill::class, 'CVAnalysisID', 'CVAnalysisID');
    }
}
