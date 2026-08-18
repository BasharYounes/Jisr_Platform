<?php

namespace App\Providers;

use App\Events\CompanyVerified;
use App\Events\LoginOtpRequested;
use App\Events\PasswordResetOtpRequested;
use App\Events\UserRegistered;
use App\Events\UserRegistrationFailed;
use App\Listeners\DeleteUploadedImage;
use App\Listeners\SendCompanyVerificationEmail;
use App\Listeners\SendLoginOtpListener;
use App\Listeners\SendResetOtpListener;
use App\Listeners\SendWelcomeNotification;
use App\Events\CompanyTaskHighMatchApplicationReceived;
use App\Listeners\SendCompanyTaskHighMatchApplicationNotification;
use App\Events\CompanyRejected;
use App\Events\CompanyTaskApplicationAccepted;
use App\Events\CompanyTaskSubmissionSubmitted;
use App\Listeners\SendCompanyRejectedEmail;
use App\Listeners\SendCompanyTaskApplicationAcceptedNotification;
use App\Listeners\SendCompanyTaskSubmissionNotification;
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

        CompanyTaskHighMatchApplicationReceived::class => [
            SendCompanyTaskHighMatchApplicationNotification::class,
        ],

        CompanyRejected::class => [
            SendCompanyRejectedEmail::class,
        ],

        CompanyTaskApplicationAccepted::class => [
            SendCompanyTaskApplicationAcceptedNotification::class,
        ],

        CompanyTaskSubmissionSubmitted::class => [
            SendCompanyTaskSubmissionNotification::class,
        ],
    ];

    public function boot(): void
    {
        parent::boot();
    }
}
