<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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


}
