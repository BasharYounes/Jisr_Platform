<?php

namespace App\Listeners;

use App\Events\LoginOtpRequested;
use App\Notifications\SendOtpNotification;


class SendLoginOtpListener
{
    public function handle(LoginOtpRequested $event): void
    {
          $event->user->notify(
        new SendOtpNotification($event->code)
    );
    }
}