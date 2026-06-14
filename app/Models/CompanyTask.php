<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyTask extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'title',
        'description',
        'difficulty_level',
        'duration_days',
        'deadline',
        'max_applicants',
        'max_accepted_students',
        'deliverables',
        'acceptance_criteria',
        'submission_type',
        'status',
        'published_at',
    ];

    protected $casts = [
        'deadline' => 'datetime',
        'published_at' => 'datetime',
        'deliverables' => 'array',
        'acceptance_criteria' => 'array',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function skills()
    {
        return $this->belongsToMany(Skill::class, 'company_task_skills')
            ->withPivot([
                'required_level',
                'weight',
                'mandatory',
            ])
            ->withTimestamps();
    }

    public function applications()
    {
        return $this->hasMany(CompanyTaskApplication::class);
    }

    public function assignments()
    {
        return $this->hasMany(CompanyTaskAssignment::class);
    }

    public function submissions(): HasManyThrough
    {
        return $this->hasManyThrough(
            CompanyTaskSubmission::class,
            CompanyTaskAssignment::class,
            'company_task_id',
            'company_task_assignment_id',
            'id',
            'id'
        );
    }
}
