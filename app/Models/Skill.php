<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Skill extends Model
{
    protected $guarded = [];

     public function opportunities()
    {
        return $this->belongsToMany(Opportunity::class)
                    ->withPivot(['required_level', 'mandatory', 'weight'])
                    ->withTimestamps();
    }

    public function users()
{
    return $this->belongsToMany(User::class)
                ->withPivot([
                    'proficiency_level',
                    'confidence_score',
                    'source',
                    'verified'
                ])
                ->withTimestamps();
}

public function trends()
{
    return $this->hasMany(MarketTrend::class);
}

public function resources()
{
    return $this->belongsToMany(LearningResources::class, 'resource_skill_mappings')
                ->withPivot('relevance_score')
                ->withTimestamps();
}

}
