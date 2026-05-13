<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyTaskSubmission extends Model
{
     use SoftDeletes;

    protected $fillable = [
        'company_task_assignment_id',
        'student_user_id',
        'github_url',
        'demo_url',
        'zip_file_path',
        'notes',
        'status',
        'submitted_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
    ];

    public function assignment()
    {
        return $this->belongsTo(CompanyTaskAssignment::class, 'company_task_assignment_id');
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_user_id');
    }

    public function review()
    {
        return $this->hasOne(CompanyTaskReview::class, 'company_task_submission_id');
    }

}
