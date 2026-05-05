<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SkillLevelDefinition extends Model
{
    protected $table = 'skill_level_definitions';
    protected $primaryKey = 'SkillLevelDefinitionID';

    protected $fillable = [
        'SkillID',
        'Level',
        'Title',
        'Description',
        'BehavioralIndicatorsJson',
    ];

    protected $casts = [
        'Level' => 'integer',
        'BehavioralIndicatorsJson' => 'array',
    ];

    public function skill()
    {
        return $this->belongsTo(Skill::class, 'SkillID', 'id');
    }
}
