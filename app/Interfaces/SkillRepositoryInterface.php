<?php

namespace App\Interfaces;

use Illuminate\Support\Collection;

interface SkillRepositoryInterface
{
    public function getAll(?string $search = null): Collection;
}
