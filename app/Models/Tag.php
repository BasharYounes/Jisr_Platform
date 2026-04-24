<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    protected $guarded = [];

    public function opportunities()
    {
        return $this->belongsToMany(Opportunity::class)
                    ->withPivot(['weight', 'mandatory'])
                    ->withTimestamps();
    }

    public function users()
    {
        return $this->belongsToMany(User::class)
                    ->withTimestamps();
    }

    public function projectTemplates()
    {
        return $this->belongsToMany(ProjectTemplate::class)
                    ->withTimestamps();
    }

    public function verificationRequests()
{
    return $this->belongsToMany(VerificationRequest::class, 'tag_verification_request')
                ->withTimestamps();
}
}
