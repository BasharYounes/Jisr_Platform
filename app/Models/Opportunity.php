<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Opportunity extends Model
{
    protected $guarded = [];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function skills()
    {
        return $this->belongsToMany(Skill::class)
            ->withPivot(['required_level', 'mandatory', 'weight'])
            ->withTimestamps();
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class)
            ->withPivot(['weight', 'mandatory'])
            ->withTimestamps();
    }

    public function applications()
    {
        return $this->hasMany(Application::class);
    }

    public function interviews(): HasMany
    {
        return $this->hasMany(OpportunityInterview::class, 'opportunity_id');
    }
}
