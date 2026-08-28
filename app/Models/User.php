<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
// use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected $guard_name = 'web';

    /**
     * The model's default values for attributes.
     *
     * Keep this aligned with the users table default so newly-created
     * Eloquent instances are considered active before a database refresh.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
        'bio',
        'profile_picture_url',
    ];

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
            'is_active' => 'boolean',
        ];
    }

    public function assessmentSessions()
    {
        return $this->hasMany(AssessmentSession::class, 'UserID', 'id');
    }

    public function createdQuestionBanks()
    {
        return $this->hasMany(QuestionBank::class, 'CreatedByUserId', 'id');
    }

    public function candidateMatchSnapshots()
    {
        return $this->hasMany(CandidateMatchSnapshot::class, 'user_id', 'id');
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

    // public function roles()
    // {
    // return $this->belongsToMany(Role::class, 'user_roles')
    //             ->withPivot('assigned_at')->withTimestamps();
    // }

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
        return $this->belongsToMany(Company::class, 'company_users')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function skills()
    {
        return $this->belongsToMany(Skill::class, 'user_skills', 'UserId', 'SkillId')
            ->withPivot([
                'ProficiencyLevel',
                'ConfidenceScore',
                'Source',
                'Verified',
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
        return $this->hasMany(
            CV::class,
            'UserId',
            'id'
        );
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

    public function otpCodes()
    {
        return $this->hasMany(OtpCode::class);
    }

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(UserDeviceToken::class);
    }

    public function opportunityInterviews(): HasMany
    {
        return $this->hasMany(OpportunityInterview::class, 'student_user_id');
    }
}
