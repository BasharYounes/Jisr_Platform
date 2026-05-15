<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvaluationCriteria extends Model
{
    protected $table = 'evaluation_criteria';

    protected $fillable = [
        'name',
        'description',
        'category',
        'max_score',
        'weight',
        'scoring_anchors',
        'skill_impacts',
        'version',
        'is_active',
        'is_required',
    ];

    protected $casts = [
        'max_score' => 'decimal:2',
        'weight' => 'decimal:2',
        'scoring_anchors' => 'array',
        'skill_impacts' => 'array',
        'version' => 'integer',
        'is_active' => 'boolean',
        'is_required' => 'boolean',
    ];

    public function items()
    {
        return $this->hasMany(EvaluationItem::class);
    }
}
