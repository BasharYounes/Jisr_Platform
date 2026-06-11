<?php

namespace App\Repositories;

use App\Interfaces\PortfolioProjectRepositoryInterface;
use App\Models\PortfolioProject;
use Illuminate\Database\Eloquent\Collection;

class PortfolioProjectRepository implements PortfolioProjectRepositoryInterface
{
    public function getByStudent(int $userId): Collection
    {
        return PortfolioProject::query()
            ->where('user_id', $userId)
            ->latest()
            ->get();
    }

    public function create(array $data): PortfolioProject
    {
        return PortfolioProject::create($data);
    }

    public function findStudentProjectOrFail(int $userId, int $projectId): PortfolioProject
    {
        return PortfolioProject::query()
            ->where('user_id', $userId)
            ->where('id', $projectId)
            ->firstOrFail();
    }

    public function update(PortfolioProject $project, array $data): PortfolioProject
    {
        $project->update($data);

        return $project->fresh();
    }

    public function delete(PortfolioProject $project): void
    {
        $project->delete();
    }
}
