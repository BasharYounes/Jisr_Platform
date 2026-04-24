<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectEvaluation extends Model
{
    protected $guarded = [];
   public function assignment()
    {
        return $this->belongsTo(ProjectAssignment::class, 'project_assignment_id');
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function items()
{
    return $this->hasMany(EvaluationItem::class);
}


}
