<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyTaskApplication extends Model
{
     use SoftDeletes;

    protected $fillable = [
        'company_task_id',
        'student_user_id',
        'message',
        'portfolio_url',
        'github_url',
        'status',
        'match_score',
        'match_reasons',
        'applied_at',
        'reviewed_at',
        'company_notes',
    ];

    protected $casts = [
        'match_score' => 'decimal:2',
        'match_reasons' => 'array',
        'applied_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function task()
    {
        return $this->belongsTo(CompanyTask::class, 'company_task_id');
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_user_id');
    }

    public function assignment()
    {
        return $this->hasOne(CompanyTaskAssignment::class);
    }
}
