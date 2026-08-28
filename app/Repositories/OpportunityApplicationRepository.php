<?php

namespace App\Repositories;

use App\Interfaces\OpportunityApplicationRepositoryInterface;
use App\Models\Application;
use Illuminate\Support\Collection;

class OpportunityApplicationRepository implements OpportunityApplicationRepositoryInterface
{
    public function existsForStudent(
        int $studentUserId,
        int $opportunityId
    ): bool {
        return Application::query()
            ->where('user_id', $studentUserId)
            ->where('opportunity_id', $opportunityId)
            ->exists();
    }

    public function findForStudentAndOpportunity(
        int $studentUserId,
        int $opportunityId
    ): ?Application {
        return Application::query()
            ->with([
                'cv',
                'interview',
            ])
            ->where('user_id', $studentUserId)
            ->where('opportunity_id', $opportunityId)
            ->first();
    }

    public function create(array $data): Application
    {
        return Application::query()
            ->create($data)
            ->load([
                'opportunity.company',
                'cv',
                'interview',
            ]);
    }

    public function getStudentApplications(int $studentUserId): Collection
    {
        return Application::query()
            ->with([
                'opportunity.company',
                'opportunity.skills',
                'cv',
                'interview',
            ])
            ->where('user_id', $studentUserId)
            ->latest('applied_at')
            ->get();
    }

    public function findStudentApplicationOrFail(
        int $studentUserId,
        int $applicationId
    ): Application {
        return Application::query()
            ->with([
                'opportunity.company',
                'opportunity.skills',
                'cv',
                'interview',
            ])
            ->whereKey($applicationId)
            ->where('user_id', $studentUserId)
            ->firstOrFail();
    }

    public function update(Application $application, array $data): Application
    {
        $application->update($data);

        return $application->fresh([
            'opportunity.company',
            'opportunity.skills',
            'cv',
            'interview',
        ]);
    }

    public function getOpportunityCandidates(
        int $companyId,
        int $opportunityId
    ): Collection {
        return Application::query()
            ->with([
                'user.studentProfile',
                'opportunity.company',
                'opportunity.skills',
                'cv',
                'interview',
            ])
            ->where('opportunity_id', $opportunityId)
            ->whereHas('opportunity', function ($query) use ($companyId): void {
                $query->where('company_id', $companyId);
            })
            ->orderByDesc('match_score')
            ->latest('applied_at')
            ->get();
    }

    public function findCompanyCandidateOrFail(
        int $companyId,
        int $opportunityId,
        int $applicationId
    ): Application {
        return Application::query()
            ->with([
                'user.studentProfile',

                'user.skills' => fn ($query) => $query
                    ->orderBy('skills.name'),

                'user.portfolioProjects' => fn ($query) => $query
                    ->orderByDesc('completion_date')
                    ->orderByDesc('id'),

                'user.studentProjectAssignments' => fn ($query) => $query
                    ->orderByDesc('project_assignments.assigned_at')
                    ->orderByDesc('project_assignments.id'),

                'user.studentProjectAssignments.projectTemplate',
                'user.studentProjectAssignments.supervisor',

                'user.receivedProjectEvaluations' => fn ($query) => $query
                    ->orderByDesc('evaluated_at')
                    ->orderByDesc('id'),

                'opportunity.company',
                'opportunity.skills',
                'cv',
                'interview',
            ])
            ->whereKey($applicationId)
            ->where('opportunity_id', $opportunityId)
            ->whereHas('opportunity', function ($query) use ($companyId): void {
                $query->where('company_id', $companyId);
            })
            ->firstOrFail();
    }
}
