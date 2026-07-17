<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    protected $guarded = [];

    public function users()
    {
        return $this->belongsToMany(User::class, 'company_users')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function reviews()
    {
        return $this->hasMany(CompanyReview::class);
    }

    public function opportunityInterviews(): HasMany
    {
        return $this->hasMany(OpportunityInterview::class, 'company_id');
    }
}
