<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketTrend extends Model
{
    protected $table = 'market_trends';

    protected $fillable = [
        'career_path_id',
        'skill_id',
        'demand_score',
        'trend_direction',
        'source_job_count',
        'analyzed_date',
    ];

    protected $casts = [
        'demand_score' => 'float',
        'source_job_count' => 'integer',
        'analyzed_date' => 'date',
    ];

    public function careerPath(): BelongsTo
    {
        return $this->belongsTo(
            CareerPath::class,
            'career_path_id',
            'CareerPathID'
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
}
