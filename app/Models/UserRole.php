<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserRole extends Model
{

    protected $guarded = [];

    public function users()
{
    return $this->belongsToMany(User::class, 'user_roles')
                ->withPivot('assigned_at')
                ->withTimestamps();
}
}
