<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Application extends Model
{
    protected $guarded = [];

    protected $casts = [
        'match_score' => 'decimal:2',
        'match_reasons' => 'array',
        'applied_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

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

    public function interview(): HasOne
    {
        return $this->hasOne(OpportunityInterview::class, 'application_id');
    }
}
