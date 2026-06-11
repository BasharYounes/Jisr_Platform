<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSkill extends Model
{
    protected $guarded = [];

    public const STATUS_SELF_DECLARED = 'self_declared';

    public const STATUS_AI_ESTIMATED = 'ai_estimated';

    public const STATUS_CODE_TESTED = 'code_tested';

    public const STATUS_SUPERVISOR_VERIFIED = 'supervisor_verified';

    public const STATUS_COMPANY_VERIFIED = 'company_verified';

    public const VERIFICATION_STATUSES = [
        self::STATUS_SELF_DECLARED,
        self::STATUS_AI_ESTIMATED,
        self::STATUS_CODE_TESTED,
        self::STATUS_SUPERVISOR_VERIFIED,
        self::STATUS_COMPANY_VERIFIED,
    ];

    protected $table = 'user_skills';

    protected $primaryKey = 'UserSkillID';

    protected $fillable = [
        'UserId',
        'SkillId',
        'ProficiencyLevel',
        'ConfidenceScore',
        'Source',
        'Verified',
        'VerificationStatus',
        'VerifiedAt',
        'VerifiedBy',
    ];

    protected $casts = [
        'ProficiencyLevel' => 'integer',
        'ConfidenceScore' => 'decimal:2',
        'Verified' => 'boolean',
        'VerifiedAt' => 'datetime',
        'VerifiedBy' => 'integer',
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
