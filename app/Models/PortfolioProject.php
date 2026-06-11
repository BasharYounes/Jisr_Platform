<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortfolioProject extends Model
{
    protected $guarded = [];

    protected $casts = [
        'completion_date' => 'datetime',
        'grade' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function portfolioable()
    {
        return $this->morphTo();
    }
}
