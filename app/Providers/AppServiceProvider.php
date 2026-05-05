<?php

namespace App\Providers;

use App\Services\AI\AIClientInterface;
use App\Services\AI\GeminiClient;
use App\Services\AI\MockAIClient;
use Illuminate\Support\ServiceProvider;

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
