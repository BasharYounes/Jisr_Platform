<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupervisorProfile extends Model
{

    protected $guarded = [];

    public function user()
{
    return $this->belongsTo(User::class);
}
}
