<?php

namespace App\Providers;

use App\Services\AI\AIClientInterface;
use App\Services\AI\GeminiClient;
use App\Services\AI\MockAIClient;
use Illuminate\Support\ServiceProvider;
use App\Interfaces\CompanyTaskRepositoryInterface;
use App\Interfaces\PortfolioProjectRepositoryInterface;
use App\Interfaces\StudentSkillRepositoryInterface;
use App\Repositories\CompanyTaskRepository;
use App\Repositories\PortfolioProjectRepository;
use App\Repositories\StudentSkillRepository;

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

        $this->app->bind(AIClientInterface::class, function () {
            return env('AI_PROVIDER') === 'gemini'
                ? app(GeminiClient::class)
                : app(MockAIClient::class);
        });
    }

    public function boot(): void
    {
        //
    }
}
