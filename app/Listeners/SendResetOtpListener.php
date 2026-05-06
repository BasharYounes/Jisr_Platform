<?php

namespace App\Listeners;

use App\Events\PasswordResetOtpRequested;
use App\Notifications\SendOtpNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendResetOtpListener
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
   public function handle(PasswordResetOtpRequested $event): void
    {
        $event->user->notify(
            new SendOtpNotification(
                code: $event->code,
                type: 'password_reset'
            )
        );
    
    }
}
