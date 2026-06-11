<?php

namespace App\Listeners;

use App\Events\UserRegistrationFailed;
use Illuminate\Support\Facades\Storage;

class DeleteUploadedImage
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
    public function handle(UserRegistrationFailed $event): void
    {
        if ($event->imagePath) {
            Storage::disk('public')->delete($event->imagePath);
        }
    }
}
