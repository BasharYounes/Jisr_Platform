<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
    ];

    protected $casts = [
        'total_score' => 'decimal:2',
        'final_grade' => 'decimal:2',
        'summary_metrics' => 'array',
        'evaluated_at' => 'datetime',
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
}
