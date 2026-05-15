<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectTemplate extends Model
{
    protected $guarded = [];
      public function assignments()
    {
        return $this->hasMany(ProjectAssignment::class);
    }

    public function tasks()
    {
        return $this->hasMany(ProjectTask::class, 'project_template_id')->orderBy('order_index');
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class)
                    ->withTimestamps();
    }

}
