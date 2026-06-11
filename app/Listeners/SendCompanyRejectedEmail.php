<?php

namespace App\Listeners;

use App\Events\CompanyRejected;
use App\Notifications\CompanyRejectedNotification;

class SendCompanyRejectedEmail
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
    public function handle(CompanyRejected $event): void
    {
        $event->user->notify(new CompanyRejectedNotification($event->company));

    }
}
