<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationApplication extends Model
{
    protected $guarded=[];

    public function notification()
    {
        return $this->belongsTo(Notification::class);
    }

    public function application()
    {
        return $this->belongsTo(Application::class);
    }
    
}
