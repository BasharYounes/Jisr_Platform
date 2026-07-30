<?php

namespace App\Models;

use App\Domains\Supervisor\Enums\ProjectEvaluationAppealStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectEvaluationAppeal extends Model
{
    protected $fillable = [
        'project_evaluation_id',
        'student_id',
        'reason',
        'status',
        'evaluation_snapshot',
        'reviewed_by',
        'review_notes',
        'reviewed_at',
        'revision_request_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProjectEvaluationAppealStatus::class,

            'evaluation_snapshot' => 'array',

            'reviewed_at' => 'datetime',

            'project_evaluation_id' => 'integer',
            'student_id' => 'integer',
            'reviewed_by' => 'integer',
            'revision_request_id' => 'integer',
        ];
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(
            ProjectEvaluation::class,
            'project_evaluation_id'
        );
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'student_id'
        );
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'reviewed_by'
        );
    }

    public function revisionRequest(): BelongsTo
    {
        return $this->belongsTo(
            EvaluationRevisionRequest::class,
            'revision_request_id'
        );
    }
}
