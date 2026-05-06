<?php

namespace App\Listeners;

use App\Events\CompanyVerified;
use App\Notifications\CompanyVerifiedNotification;

class SendCompanyVerificationEmail
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(CompanyVerified $event): void
    {
        $event->user->notify(new CompanyVerifiedNotification($event->company));
    }
}
