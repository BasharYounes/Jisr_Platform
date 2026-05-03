<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentSkillGapRecommendation extends Model
{
    protected $guarded =[];
    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function resource()
    {
        return $this->belongsTo(LearningResources::class, 'resource_id');
    }

    public function skill()
    {
        return $this->belongsTo(Skill::class, 'gap_skill_id');
    }
}
