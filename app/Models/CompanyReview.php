<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyReview extends Model
{
    protected $guraded=[];

    public function company()
{
    return $this->belongsTo(Company::class);
}

public function user()
{
    return $this->belongsTo(User::class);
} 
}
