<?php
namespace App\Events;

use App\Models\User;

class LoginOtpRequested
{
    public function __construct(
        public User $user,
        public string $code
    ) {}
}