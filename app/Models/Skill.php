<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Skill extends Model
{
    protected $table = 'skills';
    protected $primaryKey = 'id';

    protected $fillable = [
        'name',
        'category',
        'normalized_name',
    ];

    public function aliases()
    {
        return $this->hasMany(SkillAlias::class, 'SkillID', 'id');
    }

    public function levelDefinitions()
    {
        return $this->hasMany(SkillLevelDefinition::class, 'SkillID', 'id');
    }

    public function questionBanks()
    {
        return $this->hasMany(QuestionBank::class, 'SkillID', 'id');
    }

    public function careerPathSkills()
    {
        return $this->hasMany(CareerPathSkill::class, 'SkillID', 'id');
    }

    public function careerPaths()
    {
        return $this->belongsToMany(CareerPath::class, 'career_path_skills', 'SkillID', 'CareerPathID')
            ->withPivot(['RequiredLevel', 'Weight', 'IsCore'])
            ->withTimestamps();
    }

    public function assessmentSkillSessions()
    {
        return $this->hasMany(AssessmentSkillSession::class, 'SkillID', 'id');
    }

    public function cvExtractedSkills()
    {
        return $this->hasMany(CVExtractedSkill::class, 'SkillID', 'id');
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }
}
