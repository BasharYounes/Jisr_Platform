<?php

namespace App\Services\Skill;

use App\Interfaces\SkillRepositoryInterface;
use Illuminate\Support\Collection;

class SkillService
{
    public function __construct(
        private readonly SkillRepositoryInterface $skillRepository
    ) {}

    public function getAllSkills(?string $search = null): Collection
    {
        return $this->skillRepository->getAll($search);
    }
}