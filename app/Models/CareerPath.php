<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CareerPath extends Model
{
    protected $table = 'career_paths';
    protected $primaryKey = 'CareerPathID';

    protected $fillable = [
        'Name',
        'Description',
    ];

    public function careerPathSkills()
    {
        return $this->hasMany(CareerPathSkill::class, 'CareerPathID', 'CareerPathID');
    }

    public function skills()
    {
        return $this->belongsToMany(Skill::class, 'career_path_skills', 'CareerPathID', 'SkillID')
            ->withPivot(['RequiredLevel', 'Weight', 'IsCore'])
            ->withTimestamps();
    }

    public function questionBanks()
    {
        return $this->hasMany(QuestionBank::class, 'CareerPathID', 'CareerPathID');
    }

    public function assessmentSessions()
    {
        return $this->hasMany(AssessmentSession::class, 'CareerPathID', 'CareerPathID');
    }
}
