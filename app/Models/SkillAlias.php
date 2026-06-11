<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SkillAlias extends Model
{
    protected $table = 'skill_aliases';

    protected $primaryKey = 'SkillAliasID';

    protected $fillable = [
        'SkillID',
        'Alias',
        'LanguageCode',
    ];

    public function skill()
    {
        return $this->belongsTo(Skill::class, 'SkillID', 'id');
    }
}
