<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MentorReview extends Model
{
    protected $guraded=[];

    public function mentor()
{
    return $this->belongsTo(User::class, 'mentor_id');
}

public function user()
{
    return $this->belongsTo(User::class, 'user_id');
}

}
