<?php

namespace App\Repositories;

use App\Interfaces\SkillRepositoryInterface;
use App\Models\Skill;
use Illuminate\Support\Collection;

class SkillRepository implements SkillRepositoryInterface
{
    public function getAll(?string $search = null): Collection
    {
        return Skill::query()
            ->select(['id', 'name', 'category'])
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search . '%');
            })
            ->orderBy('name')
            ->get();
    }
}