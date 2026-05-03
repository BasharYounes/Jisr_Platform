<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectTask extends Model
{
    protected $guarded = [];
   public function template()
    {
        return $this->belongsTo(ProjectTemplate::class, 'project_template_id');
    }
    
}
