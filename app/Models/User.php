<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function posts()
    {
    return $this->hasMany(Post::class);
    }
    public function likedPosts()
    {
    return $this->belongsToMany(Post::class, 'post_likes');
    }
    public function likedComments()
    {
    return $this->belongsToMany(Comment::class, 'comment_likes');
    }
    
    public function supervisorProfile()
    {
    return $this->hasOne(SupervisorProfile::class);
    }
    
    public function mentorProfile()
    {
    return $this->hasOne(MentorProfile::class);
    }
    public function auditLogs()
    {
    return $this->hasMany(AuditLog::class);
    }

    public function roles()
    {
    return $this->belongsToMany(Role::class, 'user_roles')
                ->withPivot('assigned_at')->withTimestamps();
    }

    public function complaintsMade()
{
    return $this->hasMany(Complaint::class, 'complainant_user_id');
}

public function complaintsReceived()
{
    return $this->hasMany(Complaint::class, 'reported_user_id');
}

    public function pointTransactions()
{
    return $this->hasMany(PointTransaction::class);
}
public function portfolioProjects()
{
    return $this->hasMany(PortfolioProject::class);
}

public function assignments()
{
    return $this->hasMany(ProjectAssignment::class);
}

public function supervisedAssignments()
{
    return $this->hasMany(ProjectAssignment::class, 'supervisor_id');
}

public function evaluations()
{
    return $this->hasMany(ProjectEvaluation::class, 'supervisor_id');
}

public function mockInterviews()
{
    return $this->hasMany(MockInterviewSession::class, 'student_id');
}

public function companies()
{
    return $this->belongsToMany(Company::class)
                ->withPivot('role')
                ->withTimestamps();
}

public function skills()
{
    return $this->belongsToMany(Skill::class)
                ->withPivot([
                    'proficiency_level',
                    'confidence_score',
                    'source',
                    'verified'
                ])
                ->withTimestamps();
}

public function tags()
{
    return $this->belongsToMany(Tag::class)
                ->withTimestamps();
}

public function studentProfile()
{
    return $this->hasOne(StudentProfile::class);
}

public function roadmapSuggestions()
{
    return $this->hasMany(RoadmapSuggestion::class, 'student_id');
}
public function cvs()
{
    return $this->hasMany(Cv::class);
}

public function verificationRequests()
{
    return $this->hasMany(VerificationRequest::class, 'applicant_user_id');
}

public function mentorReviews()
{
    return $this->hasMany(MentorReview::class, 'mentor_id');
}

public function givenReviews()
{
    return $this->hasMany(MentorReview::class, 'user_id');
}

public function companyReviews()
{
    return $this->hasMany(CompanyReview::class);
}


public function supervisorReviews()
{
    return $this->hasMany(SupervisorReview::class, 'supervisor_id');
}

public function givenSupervisorReviews()
{
    return $this->hasMany(SupervisorReview::class, 'user_id');
}

public function applications()
{
    return $this->hasMany(Application::class);
}

public function notificationPreference()
{
    return $this->hasOne(NotificationPreference::class);
}

public function notifications()
{
    return $this->hasMany(Notification::class);
}

public function receivedNotifications()
{
    return $this->hasMany(Notification::class, 'user_id');
}

public function sentNotifications()
{
    return $this->hasMany(Notification::class, 'actor_id');
}


public function conversations()
{
    return $this->belongsToMany(Conversation::class, 'conversation_participants')
                ->withPivot('role')
                ->withTimestamps();
}

public function sentMessages()
{
    return $this->hasMany(Message::class, 'sender_id');
}

}
