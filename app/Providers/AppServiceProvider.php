<?php

namespace App\Providers;

use App\Events\ProjectAssignmentReadyForEvaluation;
use App\Events\ProjectAssignmentStatusChanged;
use App\Interfaces\CompanyHomeRepositoryInterface;
use App\Interfaces\CompanyOpportunityRepositoryInterface;
use App\Interfaces\CompanyRepositoryInterface;
use App\Interfaces\CompanyTaskApplicationRepositoryInterface;
use App\Interfaces\CompanyTaskAssignmentRepositoryInterface;
use App\Interfaces\CompanyTaskProgressRepositoryInterface;
use App\Interfaces\CompanyTaskRepositoryInterface;
use App\Interfaces\CompanyTaskReviewRepositoryInterface;
use App\Interfaces\CompanyTaskSubmissionRepositoryInterface;
use App\Interfaces\ConversationParticipantRepositoryInterface;
use App\Interfaces\ConversationRepositoryInterface;
use App\Interfaces\MessageRepositoryInterface;
use App\Interfaces\NotificationRepositoryInterface;
use App\Interfaces\PortfolioProjectRepositoryInterface;
use App\Interfaces\SkillRepositoryInterface;
use App\Interfaces\StudentRepositoryInterface;
use App\Interfaces\StudentSkillRepositoryInterface;
use App\Interfaces\StudentTaskApplicationRepositoryInterface;
use App\Interfaces\SupervisorRepositoryInterface;
use App\Interfaces\UserRepositoryInterface;
use App\Listeners\CreatePortfolioProjectWhenAssignmentCompleted;
use App\Listeners\NotifyStudentProjectStatusChanged;
use App\Listeners\NotifySupervisorProjectReadyForEvaluation;
use App\Models\CompanyTaskAssignment;
use App\Models\ProjectAssignment;
use App\Models\ProjectAssignmentTask;
use App\Models\ProjectEvaluation;
use App\Models\User;
use App\Observers\ProjectAssignmentObserver;
use App\Policies\ProjectAssignmentPolicy;
use App\Policies\ProjectAssignmentTaskPolicy;
use App\Policies\ProjectEvaluationPolicy;
use App\Repositories\CompanyHomeRepository;
use App\Repositories\CompanyOpportunityRepository;
use App\Repositories\CompanyRepository;
use App\Repositories\CompanyTaskApplicationRepository;
use App\Repositories\CompanyTaskAssignmentRepository;
use App\Repositories\CompanyTaskProgressRepository;
use App\Repositories\CompanyTaskRepository;
use App\Repositories\CompanyTaskReviewRepository;
use App\Repositories\CompanyTaskSubmissionRepository;
use App\Repositories\ConversationParticipantRepository;
use App\Repositories\ConversationRepository;
use App\Repositories\MessageRepository;
use App\Repositories\NotificationRepository;
use App\Repositories\PortfolioProjectRepository;
use App\Repositories\SkillRepository;
use App\Repositories\StudentRepository;
use App\Repositories\StudentSkillRepository;
use App\Repositories\StudentTaskApplicationRepository;
use App\Repositories\SupervisorRepository;
use App\Repositories\UserRepository;
use App\Services\AI\AIClientInterface;
use App\Services\AI\GeminiClient;
use App\Services\AI\MockAIClient;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            NotificationRepositoryInterface::class,
            NotificationRepository::class
        );

        $this->app->bind(
            UserRepositoryInterface::class,
            UserRepository::class
        );

        $this->app->bind(
            StudentRepositoryInterface::class,
            StudentRepository::class
        );

        $this->app->bind(
            CompanyRepositoryInterface::class,
            CompanyRepository::class
        );

        $this->app->bind(
            CompanyTaskRepositoryInterface::class,
            CompanyTaskRepository::class
        );

        $this->app->bind(
            StudentSkillRepositoryInterface::class,
            StudentSkillRepository::class
        );

        $this->app->bind(
            PortfolioProjectRepositoryInterface::class,
            PortfolioProjectRepository::class
        );

        $this->app->bind(
            CompanyTaskApplicationRepositoryInterface::class,
            CompanyTaskApplicationRepository::class
        );

        $this->app->bind(
            CompanyTaskAssignmentRepositoryInterface::class,
            CompanyTaskAssignmentRepository::class
        );

        $this->app->bind(
            CompanyHomeRepositoryInterface::class,
            CompanyHomeRepository::class
        );

        $this->app->bind(ConversationRepositoryInterface::class, ConversationRepository::class);
        $this->app->bind(MessageRepositoryInterface::class, MessageRepository::class);
        $this->app->bind(ConversationParticipantRepositoryInterface::class, ConversationParticipantRepository::class);
        $this->app->bind(
            SkillRepositoryInterface::class,
            SkillRepository::class
        );

        $this->app->bind(
            StudentTaskApplicationRepositoryInterface::class,
            StudentTaskApplicationRepository::class);

        $this->app->bind(
            SupervisorRepositoryInterface::class,
            SupervisorRepository::class
        );

        $this->app->bind(
            CompanyTaskProgressRepositoryInterface::class,
            CompanyTaskProgressRepository::class
        );

        $this->app->bind(
            CompanyTaskSubmissionRepositoryInterface::class,
            CompanyTaskSubmissionRepository::class
        );

        $this->app->bind(
            CompanyTaskReviewRepositoryInterface::class,
            CompanyTaskReviewRepository::class
        );

        $this->app->bind(
            CompanyOpportunityRepositoryInterface::class,
            CompanyOpportunityRepository::class
        );

        $this->app->bind(
            OpportunityApplicationRepositoryInterface::class,
            OpportunityApplicationRepository::class
        );

        $this->app->bind(
            OpportunityInterviewRepositoryInterface::class,
            OpportunityInterviewRepository::class
        );

        $this->app->bind(
            ConversationRepositoryInterface::class,
            ConversationRepository::class
        );

        $this->app->bind(AIClientInterface::class, function () {
            return env('AI_PROVIDER') === 'gemini'
                ? app(GeminiClient::class)
                : app(MockAIClient::class);
        });
    }

    public function boot(): void
    {
        Gate::policy(
            ProjectAssignment::class,
            ProjectAssignmentPolicy::class
        );

        Gate::policy(
            ProjectEvaluation::class,
            ProjectEvaluationPolicy::class
        );

        ProjectAssignment::observe(ProjectAssignmentObserver::class);

        Event::listen(
            ProjectAssignmentStatusChanged::class,
            NotifyStudentProjectStatusChanged::class
        );

        Event::listen(
            ProjectAssignmentStatusChanged::class,
            CreatePortfolioProjectWhenAssignmentCompleted::class
        );

        Event::listen(
            ProjectAssignmentReadyForEvaluation::class,
            NotifySupervisorProjectReadyForEvaluation::class
        );

        Gate::policy(
            ProjectAssignmentTask::class,
            ProjectAssignmentTaskPolicy::class
        );

        Relation::enforceMorphMap([
            'user' => User::class,
            'company_task_assignment' => CompanyTaskAssignment::class,
        ]);

    }
}
