<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoadmapSuggestion extends Model
{
    protected $guarded = [];

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function aiTests()
{
    return $this->hasMany(AITest::class, 'student_id');
}
}
