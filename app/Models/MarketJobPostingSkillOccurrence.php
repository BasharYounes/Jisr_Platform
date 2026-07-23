<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketJobPostingSkillOccurrence extends Model
{
    protected $table = 'market_job_posting_skill_occurrences';

    protected $fillable = [
        'market_job_posting_id',
        'skill_id',
        'skill_alias_id',
        'matched_text',
        'language',
        'confidence',
        'extraction_method',
        'context',
    ];

    protected $casts = [
        'confidence' => 'float',
    ];

    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(
            MarketJobPosting::class,
            'market_job_posting_id'
        );
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(
            Skill::class,
            'skill_id',
            'id'
        );
    }

    public function skillAlias(): BelongsTo
    {
        return $this->belongsTo(
            SkillAlias::class,
            'skill_alias_id',
            'SkillAliasID'
        );
    }
}
