<?php

namespace App\Models;

use App\Domains\Supervisor\Enums\EvaluationRevisionRequestSource;
use App\Domains\Supervisor\Enums\EvaluationRevisionRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EvaluationRevisionRequest extends Model
{
    protected $fillable = [
        'project_evaluation_id',
        'requested_by',
        'assigned_to',
        'source',
        'source_reference_id',
        'reason',
        'status',
        'resolution_note',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'source' => EvaluationRevisionRequestSource::class,

            'status' => EvaluationRevisionRequestStatus::class,

            'source_reference_id' => 'integer',
            'resolved_at' => 'datetime',
        ];
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(
            ProjectEvaluation::class,
            'project_evaluation_id'
        );
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'requested_by'
        );
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'assigned_to'
        );
    }

    public function revisionRequests(): HasMany
    {
        return $this->hasMany(
            EvaluationRevisionRequest::class,
            'project_evaluation_id'
        );
    }

    public function pendingRevisionRequest(): HasOne
    {
        return $this->hasOne(
            EvaluationRevisionRequest::class,
            'project_evaluation_id'
        )
            ->where(
                'status',
                EvaluationRevisionRequestStatus::Pending->value
            )
            ->latestOfMany();
    }
}
