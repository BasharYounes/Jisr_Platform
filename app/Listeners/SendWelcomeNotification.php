<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Notifications\WelcomeNotification;

class SendWelcomeNotification
{
    /**
     * Create the event listener.
     */
    public function __construct() {}

    /**
     * Handle the event.
     */
    public function handle(UserRegistered $event): void
    {
        $event->user->notify(new WelcomeNotification);

    }
}
