<?php

namespace App\Providers;

use App\Listeners\CreatePortfolioProjectWhenAssignmentCompleted;
use App\Models\ProjectAssignment;
use App\Models\ProjectAssignmentTask;
use App\Policies\ProjectAssignmentPolicy;
use App\Policies\ProjectAssignmentTaskPolicy;
use App\Services\AI\AIClientInterface;
use App\Services\AI\GeminiClient;
use App\Services\AI\MockAIClient;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

use App\Interfaces\CompanyTaskRepositoryInterface;
use App\Interfaces\PortfolioProjectRepositoryInterface;
use App\Interfaces\StudentSkillRepositoryInterface;
use App\Repositories\CompanyTaskRepository;
use App\Repositories\PortfolioProjectRepository;
use App\Repositories\StudentSkillRepository;
use App\Observers\ProjectAssignmentObserver;
use App\Events\ProjectAssignmentStatusChanged;
use App\Listeners\NotifyStudentProjectStatusChanged;
use Illuminate\Support\Facades\Event;
use App\Models\ProjectEvaluation;
use App\Policies\ProjectEvaluationPolicy;
use App\Events\ProjectAssignmentReadyForEvaluation;
use App\Listeners\NotifySupervisorProjectReadyForEvaluation;
use App\Interfaces\CompanyTaskApplicationRepositoryInterface;
use App\Repositories\CompanyTaskApplicationRepository;
use App\Interfaces\CompanyTaskAssignmentRepositoryInterface;
use App\Repositories\CompanyTaskAssignmentRepository;
use App\Interfaces\CompanyHomeRepositoryInterface;
use App\Repositories\CompanyHomeRepository;


class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
         $this->app->bind(
        \App\Interfaces\UserRepositoryInterface::class,
        \App\Repositories\UserRepository::class
    );

    $this->app->bind(
        \App\Interfaces\StudentRepositoryInterface::class,
        \App\Repositories\StudentRepository::class
    );

    $this->app->bind(
        \App\Interfaces\CompanyRepositoryInterface::class,
        \App\Repositories\CompanyRepository::class
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

    }
}
