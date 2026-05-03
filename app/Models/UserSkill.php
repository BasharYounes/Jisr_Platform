<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSkill extends Model
{
    protected $table = 'user_skills';
    protected $primaryKey = 'UserSkillID';

    protected $fillable = [
        'UserId',
        'SkillId',
        'ProficiencyLevel',
        'ConfidenceScore',
        'Source',
        'Verified',
    ];

    protected $casts = [
        'ProficiencyLevel' => 'integer',
        'ConfidenceScore' => 'decimal:2',
        'Verified' => 'boolean',
    ];

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class, 'SkillId', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'UserId', 'id');
    }
}
