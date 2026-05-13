<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyTaskReview extends Model
{
      use SoftDeletes;

    protected $fillable = [
        'company_task_submission_id',
        'company_task_assignment_id',
        'company_id',
        'student_user_id',
        'quality_score',
        'commitment_score',
        'communication_score',
        'total_score',
        'final_decision',
        'feedback',
        'reviewed_at',
    ];

    protected $casts = [
        'quality_score' => 'integer',
        'commitment_score' => 'integer',
        'communication_score' => 'integer',
        'total_score' => 'decimal:2',
        'reviewed_at' => 'datetime',
    ];

    public function submission()
    {
        return $this->belongsTo(CompanyTaskSubmission::class, 'company_task_submission_id');
    }

    public function assignment()
    {
        return $this->belongsTo(CompanyTaskAssignment::class, 'company_task_assignment_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_user_id');
    }
}
