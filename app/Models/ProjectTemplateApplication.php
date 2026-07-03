<?php

namespace App\Models;

use App\Domains\Student\Enums\ProjectTemplateApplicationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectTemplateApplication extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'project_template_id',
        'student_user_id',
        'project_assignment_id',
        'message',
        'status',
        'applied_at',
        'reviewed_at',
        'supervisor_notes',
    ];

    protected $casts = [
        'status' => ProjectTemplateApplicationStatus::class,
        'applied_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function projectTemplate()
    {
        return $this->belongsTo(ProjectTemplate::class);
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_user_id');
    }

    public function projectAssignment()
    {
        return $this->belongsTo(ProjectAssignment::class);
    }
}
