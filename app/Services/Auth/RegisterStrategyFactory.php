<?php

namespace App\Services\Auth;

use App\Services\Auth\Strategies\CompanyRegisterStrategy;
use App\Services\Auth\Strategies\RegisterStrategyInterface;
use App\Services\Auth\Strategies\StudentRegisterStrategy;
use App\Services\Auth\Strategies\SupervisorRegisterStrategy;

class RegisterStrategyFactory
{
    public static function make(string $role): RegisterStrategyInterface
    {
        return match ($role) {
            'student' => app(StudentRegisterStrategy::class),
            'company' => app(CompanyRegisterStrategy::class),
            'supervisor' => app(SupervisorRegisterStrategy::class),
            default => throw new \Exception('Invalid role'),
        };
    }
}
