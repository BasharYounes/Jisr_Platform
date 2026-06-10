<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class CompanyTaskAssignment extends Model
{
      use SoftDeletes;

    protected $fillable = [
        'company_task_id',
        'company_task_application_id',
        'student_user_id',
        'status',
        'started_at',
        'submitted_at',
        'completed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function task()
    {
        return $this->belongsTo(CompanyTask::class, 'company_task_id');
    }

    public function application()
    {
        return $this->belongsTo(CompanyTaskApplication::class, 'company_task_application_id');
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_user_id');
    }

    public function progressUpdates()
    {
        return $this->hasMany(CompanyTaskProgressUpdate::class);
    }

    public function submissions()
    {
        return $this->hasMany(CompanyTaskSubmission::class);
    }

    public function reviews()
    {
        return $this->hasMany(CompanyTaskReview::class);
    }

    public function portfolioProject()
{
    return $this->morphOne(PortfolioProject::class, 'portfolioable');
}

public function conversation()
{
    return $this->morphOne(Conversation::class, 'conversationable');
}
}
