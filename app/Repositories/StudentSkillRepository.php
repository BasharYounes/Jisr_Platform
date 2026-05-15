<?php

namespace App\Repositories;

use App\Interfaces\StudentSkillRepositoryInterface;
use App\Models\UserSkill;
use Illuminate\Support\Collection;

class StudentSkillRepository implements StudentSkillRepositoryInterface
{
    public function getSkillsForStudent(int $studentUserId): Collection
    {
        return UserSkill::query()
            ->where('UserId', $studentUserId)
            ->get()
            ->keyBy('SkillId');
    }
}