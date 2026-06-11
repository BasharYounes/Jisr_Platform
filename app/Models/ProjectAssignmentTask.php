<?php

namespace App\Models;

use App\Domains\Supervisor\Enums\ProjectAssignmentTaskStatus;
use Illuminate\Database\Eloquent\Model;

class ProjectAssignmentTask extends Model
{
    protected $fillable = [
        'project_assignment_id',
        'project_task_id',
        'assigned_student_id',
        'title',
        'description',
        'status',
        'estimated_hours',
        'submission_url',
        'github_branch_or_link',
        'supervisor_feedback',
        'started_at',
        'submitted_at',
        'reviewed_at',
        'completed_at',
        'order_index',
    ];

    protected $casts = [
        'status' => ProjectAssignmentTaskStatus::class,
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function assignment()
    {
        return $this->belongsTo(ProjectAssignment::class, 'project_assignment_id');
    }

    public function templateTask()
    {
        return $this->belongsTo(ProjectTask::class, 'project_task_id');
    }

    public function assignedStudent()
    {
        return $this->belongsTo(User::class, 'assigned_student_id');
    }
}
