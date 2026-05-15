<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvaluationItem extends Model
{
    protected $fillable = [
        'project_evaluation_id',
        'evaluation_criteria_id',
        'score',
        'comment',
        'evidence',
        'evidence_urls',
    ];

    protected $casts = [
        'score' => 'decimal:2',
        'evidence_urls' => 'array',
    ];

    public function evaluation()
    {
        return $this->belongsTo(ProjectEvaluation::class, 'project_evaluation_id');
    }

    public function criteria()
    {
        return $this->belongsTo(EvaluationCriteria::class, 'evaluation_criteria_id');
    }

}
