<?php

namespace App\Providers;

use App\Events\CompanyVerified;
use App\Events\LoginOtpRequested;
use App\Events\PasswordResetOtpRequested;
use App\Events\UserRegistrationFailed;
use App\Events\UserRegistered;
use App\Listeners\DeleteUploadedImage;
use App\Listeners\SendCompanyVerificationEmail;
use App\Listeners\SendLoginOtpListener;
use App\Listeners\SendResetOtpListener;
use App\Listeners\SendWelcomeNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected static $shouldDiscoverEvents = false;

    protected $listen = [
        LoginOtpRequested::class => [
            SendLoginOtpListener::class,
        ],

        UserRegistrationFailed::class => [
            DeleteUploadedImage::class,
        ],

        UserRegistered::class => [
            SendWelcomeNotification::class,
        ],

        PasswordResetOtpRequested::class => [
            SendResetOtpListener::class,
        ],

        CompanyVerified::class => [
            SendCompanyVerificationEmail::class,
        ],
    ];

    public function boot(): void
    {
        parent::boot();
    }
}
