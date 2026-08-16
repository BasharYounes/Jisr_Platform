<?php

namespace App\Models;

use App\Enums\MentorApplicationSource;
use App\Enums\MentorApplicationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MentorProfile extends Model
{
    protected $fillable = [
        'user_id',
        'submitted_by_user_id',
        'company_id',
        'source',
        'status',
        'full_name',
        'email',
        'whatsapp_number',
        'specialization',
        'professional_title',
        'expertise',
        'bio',
        'linkedin_url',
        'github_or_portfolio_url',
        'cv_path',
        'mentoring_topics',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'is_volunteer',
        'hourly_rate',
    ];

    protected function casts(): array
    {
        return [
            'source' => MentorApplicationSource::class,
            'status' => MentorApplicationStatus::class,
            'mentoring_topics' => 'array',
            'reviewed_at' => 'datetime',
            'is_volunteer' => 'boolean',
            'hourly_rate' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(
            Skill::class,
            'mentor_profile_skills',
            'mentor_profile_id',
            'skill_id'
        )->withTimestamps();
    }
}
