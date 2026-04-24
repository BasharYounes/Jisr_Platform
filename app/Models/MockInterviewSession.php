<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MockInterviewSession extends Model
{
    protected $guarded = [];
   public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
