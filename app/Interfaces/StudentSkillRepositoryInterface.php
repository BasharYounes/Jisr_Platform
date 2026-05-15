<?php

namespace App\Interfaces;

use Illuminate\Support\Collection;

interface StudentSkillRepositoryInterface
{
    public function getSkillsForStudent(int $studentUserId): Collection;
}