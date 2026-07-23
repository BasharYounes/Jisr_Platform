<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketJobPosting extends Model
{
    protected $table = 'market_job_postings';

    protected $fillable = [
        'source_type',
        'source_name',
        'external_id',
        'url',
        'title',
        'description',
        'company_name',
        'location',
        'language',
        'career_path_id',
        'published_at',
        'imported_at',
        'status',
        'content_hash',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'imported_at' => 'datetime',
    ];

    public function careerPath(): BelongsTo
    {
        return $this->belongsTo(
            CareerPath::class,
            'career_path_id',
            'CareerPathID'
        );
    }

    public function skillOccurrences(): HasMany
    {
        return $this->hasMany(
            MarketJobPostingSkillOccurrence::class,
            'market_job_posting_id'
        );
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(
            Skill::class,
            'market_job_posting_skill_occurrences',
            'market_job_posting_id',
            'skill_id'
        )
            ->withPivot([
                'skill_alias_id',
                'matched_text',
                'language',
                'confidence',
                'extraction_method',
                'context',
            ])
            ->withTimestamps();
    }
}
