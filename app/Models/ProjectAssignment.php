<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectAssignment extends Model
{
    protected $guarded = [];
     public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function template()
    {
        return $this->belongsTo(ProjectTemplate::class, 'project_template_id');
    }

    public function portfolioProject()
    {
        return $this->hasOne(PortfolioProject::class);
    }

    public function evaluation()
{
    return $this->hasOne(ProjectEvaluation::class);
}
}
