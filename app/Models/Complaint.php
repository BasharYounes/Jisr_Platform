<?php

namespace App\Models;

use App\Enums\ComplaintContextType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Complaint extends Model
{
    protected $fillable = [
        'complainant_user_id',
        'reported_user_id',
        'reported_mentor_profile_id',
        'context_type',
        'context_id',
        'reason',
        'status',
        'resolved_at',
        'resolution_notes',
        'deduplication_key',
    ];

    protected function casts(): array
    {
        return [
            'context_type' => ComplaintContextType::class,
            'resolved_at' => 'datetime',
        ];
    }

    public function complainant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'complainant_user_id');
    }

    public function reportedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_user_id');
    }

    public function reportedMentorProfile(): BelongsTo
    {
        return $this->belongsTo(
            MentorProfile::class,
            'reported_mentor_profile_id'
        );
    }

    public function isClosed(): bool
    {
        return in_array($this->status, ['resolved', 'rejected'], true);
    }

    public function targetType(): string
    {
        return $this->reported_mentor_profile_id !== null
            ? 'mentor'
            : 'user';
    }
}
