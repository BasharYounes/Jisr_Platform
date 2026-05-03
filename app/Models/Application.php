<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Application extends Model
{
    protected $guarded=[];
    public function user()
{
    return $this->belongsTo(User::class);
}

public function opportunity()
{
    return $this->belongsTo(Opportunity::class);
}

public function cv()
{
    return $this->belongsTo(Cv::class);
}
}
