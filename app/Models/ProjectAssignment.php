<?php

namespace App\Models;

use App\Domains\Supervisor\Enums\ProjectAssignmentStatus;
use Illuminate\Database\Eloquent\Model;

class ProjectAssignment extends Model
{
    protected $fillable = [
        'project_template_id',
        'supervisor_id',
        'status',
        'progress_percentage',
        'submission_url',
        'github_link',
        'assigned_at',
        'submitted_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'submitted_at' => 'datetime',
        'status' => ProjectAssignmentStatus::class,
    ];

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function projectTemplate()
    {
        return $this->belongsTo(ProjectTemplate::class);
    }

    public function portfolioProject()
    {
        return $this->morphOne(PortfolioProject::class, 'portfolioable');
    }

    public function revisionRequests()
    {
        return $this->hasMany(ProjectRevisionRequest::class, 'project_assignment_id');
    }

    public function latestRevisionRequest()
    {
        return $this->hasOne(ProjectRevisionRequest::class, 'project_assignment_id')->latestOfMany();
    }

    public function assignmentTasks()
    {
        return $this->hasMany(ProjectAssignmentTask::class, 'project_assignment_id');
    }

    public function evaluation()
    {
        return $this->hasOne(ProjectEvaluation::class, 'project_assignment_id');
    }

    public function evaluations()
    {
        return $this->hasMany(
            ProjectEvaluation::class,
            'project_assignment_id'
        );
    }

    public function latestEvaluation()
    {
        return $this->hasOne(
            ProjectEvaluation::class,
            'project_assignment_id'
        )->latestOfMany();
    }

    public function members()
    {
        return $this->hasMany(ProjectAssignmentMember::class, 'project_assignment_id');
    }

    public function students()
    {
        return $this->belongsToMany(
            User::class,
            'project_assignment_members',
            'project_assignment_id',
            'student_id'
        )->withPivot([
            'role',
            'status',
        ])->withTimestamps();
    }
}

/*

*/
