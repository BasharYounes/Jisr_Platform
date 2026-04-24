<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LearningResources extends Model
{
    protected $guarded=[];

     public function skills()
    {
        return $this->belongsToMany(Skill::class, 'resource_skill_mappings')
                    ->withPivot('relevance_score')
                    ->withTimestamps();
    }

    public function recommendations()
    {
        return $this->hasMany(StudentSkillGapRecommendation::class, 'resource_id');
    }
}
