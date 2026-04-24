<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupervisorReview extends Model
{
    protected $guraded=[];
    public function supervisor()
{
    return $this->belongsTo(User::class, 'supervisor_id');
}

public function user()
{
    return $this->belongsTo(User::class);
}
}
