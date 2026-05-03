<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationComment extends Model
{
    protected $guarded=[];

    public function notification()
    {
        return $this->belongsTo(Notification::class);
    }

    public function comment()
    {
        return $this->belongsTo(Comment::class);
    }
    
}
