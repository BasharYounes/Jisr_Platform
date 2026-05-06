<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Complaint extends Model
{
        protected $guarded = [];

    
public function complainant()
{
    return $this->belongsTo(User::class, 'complainant_user_id');
}

public function reportedUser()
{
    return $this->belongsTo(User::class, 'reported_user_id');
}
}
