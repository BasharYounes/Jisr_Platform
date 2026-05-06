<?php

namespace App\Services\Auth\Strategies;

interface RegisterStrategyInterface
{
    public function register(array $data): array;
    
}