<?php

namespace App\Services\User;

use App\Interfaces\PortfolioProjectRepositoryInterface;
use App\Models\PortfolioProject;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class PortfolioProjectService
{
    public function __construct(
        private readonly PortfolioProjectRepositoryInterface $portfolioProjectRepository
    ) {}

    public function getStudentProjects(int $userId): Collection
    {
        return $this->portfolioProjectRepository->getByStudent($userId);
    }

    public function createManualProject(int $userId, array $data): PortfolioProject
    {
        return $this->portfolioProjectRepository->create([
            ...$data,
            'user_id' => $userId,
            'source' => 'manual',
            'portfolioable_type' => null,
            'portfolioable_id' => null,
        ]);
    }

    public function getStudentProject(int $userId, int $projectId): PortfolioProject
    {
        return $this->portfolioProjectRepository->findStudentProjectOrFail(
            userId: $userId,
            projectId: $projectId
        );
    }

    public function updateStudentProject(int $userId, int $projectId, array $data): PortfolioProject
    {
        $project = $this->portfolioProjectRepository->findStudentProjectOrFail(
            userId: $userId,
            projectId: $projectId
        );

        $this->ensureProjectCanBeModified($project);

        return $this->portfolioProjectRepository->update($project, $data);
    }

    public function deleteStudentProject(int $userId, int $projectId): void
    {
        $project = $this->portfolioProjectRepository->findStudentProjectOrFail(
            userId: $userId,
            projectId: $projectId
        );

        $this->ensureProjectCanBeModified($project);

        $this->portfolioProjectRepository->delete($project);
    }

    private function ensureProjectCanBeModified(PortfolioProject $project): void
    {
        if ($project->source !== 'manual') {
            throw ValidationException::withMessages([
                'portfolio_project' => [
                    'لا يمكن تعديل أو حذف مشروع تم إنشاؤه تلقائيًا من النظام. | System-generated portfolio projects cannot be modified or deleted manually.',
                ],
            ]);
        }
    }
}
