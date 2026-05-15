<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectRevisionRequest extends Model
{
    protected $fillable = [
        'project_assignment_id',
        'supervisor_id',
        'comment',
        'status',
    ];

    public function assignment()
    {
        return $this->belongsTo(ProjectAssignment::class, 'project_assignment_id');
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }
}
