<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyTaskProgressUpdate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_task_assignment_id',
        'student_user_id',
        'title',
        'description',
        'progress_percentage',
        'github_url',
        'demo_url',
        'attachments',
    ];

    protected $casts = [
        'attachments' => 'array',
    ];

    public function assignment()
    {
        return $this->belongsTo(CompanyTaskAssignment::class, 'company_task_assignment_id');
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_user_id');
    }
}
