<?php

namespace App\Interfaces;

use App\Models\PortfolioProject;
use Illuminate\Database\Eloquent\Collection;

interface PortfolioProjectRepositoryInterface
{
    public function getByStudent(int $userId): Collection;

    public function create(array $data): PortfolioProject;

    public function findStudentProjectOrFail(int $userId, int $projectId): PortfolioProject;

    public function update(PortfolioProject $project, array $data): PortfolioProject;

    public function delete(PortfolioProject $project): void;
}
