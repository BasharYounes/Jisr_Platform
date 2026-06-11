<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LearningResource extends Model
{
    protected $table = 'learning_resources';

    protected $primaryKey = 'LearningResourceID';

    public $timestamps = false;

    protected $fillable = [
        'SkillID',
        'Title',
        'Url',
        'Type',
        'Level',
        'EstimatedHours',
        'Provider',
        'Language',
        'IsFree',
        'IsActive',
    ];

    protected $casts = [
        'IsFree' => 'boolean',
        'IsActive' => 'boolean',
    ];

    public function skill()
    {
        return $this->belongsTo(Skill::class, 'SkillID');
    }
}
