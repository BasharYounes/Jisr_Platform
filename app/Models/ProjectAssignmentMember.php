<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectAssignmentMember extends Model
{
    protected $table = 'project_assignment_members';
    protected $fillable = [
        'project_assignment_id',
        'student_id',
        'role',
        'status',
    ];

    public function assignment()
    {
        return $this->belongsTo(ProjectAssignment::class, 'project_assignment_id');
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
