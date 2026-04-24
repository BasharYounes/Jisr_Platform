<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $guarded=[];

     public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function application()
    {
        return $this->hasOne(NotificationApplication::class);
    }

    public function comment()
    {
        return $this->hasOne(NotificationComment::class);
    }
    
}
