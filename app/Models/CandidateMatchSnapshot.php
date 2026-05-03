<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CandidateMatchSnapshot extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'opportunity_id',
        'user_id',
        'skill_score',
        'project_score',
        'activity_score',
        'tag_score',
        'freshness_score',
        'final_score',
        'rank',
        'explanation_json',
        'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'skill_score' => 'decimal:2',
            'project_score' => 'decimal:2',
            'activity_score' => 'decimal:2',
            'tag_score' => 'decimal:2',
            'freshness_score' => 'decimal:2',
            'final_score' => 'decimal:2',
            'rank' => 'integer',
            'explanation_json' => 'array',
            'calculated_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
