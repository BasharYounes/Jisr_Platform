<?php

namespace App\Repositories;

use App\Interfaces\CompanyTaskRepositoryInterface;
use App\Models\CompanyTask;
use Illuminate\Database\Eloquent\Collection;

class CompanyTaskRepository implements CompanyTaskRepositoryInterface
{
    public function create(array $data): CompanyTask
    {
        return CompanyTask::create($data);
    }

    public function update(CompanyTask $task, array $data): CompanyTask
    {
        $task->update($data);

        return $task->fresh(['company', 'skills']);
    }

    public function findCompanyTaskOrFail(int $companyId, int $taskId): CompanyTask
    {
        return CompanyTask::query()
            ->with(['company', 'skills'])
            ->where('company_id', $companyId)
            ->where('id', $taskId)
            ->firstOrFail();
    }

    public function getByCompany(int $companyId): Collection
    {
        return CompanyTask::query()
            ->with(['skills'])
            ->where('company_id', $companyId)
            ->latest()
            ->get();
    }

    public function syncSkills(CompanyTask $task, array $skills): void
    {
        $syncData = [];

        foreach ($skills as $skill) {
            $syncData[$skill['skill_id']] = [
                'required_level' => $skill['required_level'] ?? null,
                'weight' => $skill['weight'] ?? 1.00,
                'mandatory' => $skill['mandatory'] ?? true,
            ];
        }

        $task->skills()->sync($syncData);
    }

    public function publish(CompanyTask $task): CompanyTask
    {
        $task->update([
            'status' => 'published',
            'published_at' => now(),
        ]);

        return $task->fresh(['company', 'skills']);
    }

    public function getExploreTasks(?string $title = null): Collection
{
    return CompanyTask::query()
        ->with(['company', 'skills'])
        ->where('status', 'published')
        ->where('deadline', '>=', now())
        ->when($title, function ($query) use ($title) {
            $query->where('title', 'like', '%' . $title . '%');
        })
        ->latest('published_at')
        ->get();
}

public function getAvailableTasksWithSkills(): Collection
{
    return CompanyTask::query()
        ->with(['company', 'skills'])
        ->where('status', 'published')
        ->where('deadline', '>=', now())
        ->latest('published_at')
        ->get();
}

public function findAvailableTaskOrFail(int $taskId): CompanyTask
{
    return CompanyTask::query()
        ->with(['company', 'skills'])
        ->where('id', $taskId)
        ->where('status', 'published')
        ->where('deadline', '>=', now())
        ->firstOrFail();
}
}