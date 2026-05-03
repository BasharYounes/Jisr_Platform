<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvaluationItem extends Model
{
    protected $guarded = [];
    public function evaluation()
    {
        return $this->belongsTo(ProjectEvaluation::class, 'project_evaluation_id');
    }

    public function criteria()
    {
        return $this->belongsTo(EvaluationCriteria::class, 'evaluation_criteria_id');
    }
}
