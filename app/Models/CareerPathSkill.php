<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CareerPathSkill extends Model
{
    protected $table = 'career_path_skills';
    protected $primaryKey = 'CareerPathSkillID';

    protected $fillable = [
        'CareerPathID',
        'SkillID',
        'RequiredLevel',
        'Weight',
        'IsCore',
    ];

    protected $casts = [
        'RequiredLevel' => 'decimal:1',
        'Weight' => 'decimal:2',
        'IsCore' => 'boolean',
    ];

    public function careerPath()
    {
        return $this->belongsTo(CareerPath::class, 'CareerPathID', 'CareerPathID');
    }

    public function skill()
    {
        return $this->belongsTo(Skill::class, 'SkillID', 'id');
    }
}
