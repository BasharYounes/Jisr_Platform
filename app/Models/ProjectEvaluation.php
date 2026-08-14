<?php

namespace App\Models;

use App\Domains\Supervisor\Enums\EvaluationRevisionRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProjectEvaluation extends Model
{
    protected $fillable = [
        'project_assignment_id',
        'student_id',
        'supervisor_id',
        'total_score',
        'final_grade',
        'status',
        'general_comment',
        'summary_metrics',
        'evaluated_at',
        'appeal_started_at',
        'appeal_deadline_at',
    ];

    protected $casts = [
        'total_score' => 'decimal:2',
        'final_grade' => 'decimal:2',
        'summary_metrics' => 'array',
        'evaluated_at' => 'datetime',
        'appeal_started_at' => 'datetime',
        'appeal_deadline_at' => 'datetime',
    ];

    public function assignment()
    {
        return $this->belongsTo(
            ProjectAssignment::class,
            'project_assignment_id'
        );
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function items()
    {
        return $this->hasMany(
            EvaluationItem::class,
            'project_evaluation_id'
        );
    }

    public function revisionRequests(): HasMany
    {
        return $this->hasMany(
            EvaluationRevisionRequest::class,
            'project_evaluation_id'
        );
    }

    public function latestRevisionRequest(): HasOne
    {
        return $this->hasOne(
            EvaluationRevisionRequest::class,
            'project_evaluation_id'
        )->latestOfMany();
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

    public function appeals(): HasMany
    {
        return $this->hasMany(
            ProjectEvaluationAppeal::class,
            'project_evaluation_id'
        );
    }

    public function initializeAppealWindowIfMissing(): void
    {
        /*
        * إذا بدأت النافذة سابقًا فلا نغيّرها.
        * هذا يمنع إعادة تشغيل 48 ساعة بعد تعديل التقييم.
        */
        if (
            $this->appeal_started_at !== null
            && $this->appeal_deadline_at !== null
        ) {
            return;
        }

        $startedAt = $this->appeal_started_at ?? now();

        $deadlineAt = $this->appeal_deadline_at
            ?? $startedAt
                ->copy()
                ->addHours(
                    (int) config(
                        'evaluations.appeal_window_hours',
                        48
                    )
                );

        $this->forceFill([
            'appeal_started_at' => $startedAt,
            'appeal_deadline_at' => $deadlineAt,
        ])->save();
    }

    public function isAppealWindowOpen(): bool
    {
        return $this->appeal_deadline_at !== null
            && now()->lessThanOrEqualTo(
                $this->appeal_deadline_at
            );
    }
}
