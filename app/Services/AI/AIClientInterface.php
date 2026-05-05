<?php

namespace App\Services\AI;

interface AIClientInterface
{
    public function generateJson(string $systemPrompt, string $userPrompt): array;
}
