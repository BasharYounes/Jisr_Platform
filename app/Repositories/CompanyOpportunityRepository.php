<?php

namespace App\Repositories;

use App\Interfaces\CompanyOpportunityRepositoryInterface;
use App\Models\Opportunity;
use Illuminate\Database\Eloquent\Collection;

class CompanyOpportunityRepository implements CompanyOpportunityRepositoryInterface
{
    public function getByCompany(
        int $companyId,
        ?string $status = null,
        ?string $type = null,
        ?string $search = null
    ): Collection {
        return Opportunity::query()
            ->with([
                'company',
                'skills',
            ])
            ->withCount([
                'applications',
            ])
            ->where('company_id', $companyId)
            ->when($status !== null, function ($query) use ($status): void {
                $query->where('status', $status);
            })
            ->when($type !== null, function ($query) use ($type): void {
                $query->where('type', $type);
            })
            ->when($search !== null, function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('title', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%')
                        ->orWhere('location', 'like', '%'.$search.'%');
                });
            })
            ->latest()
            ->get();
    }

    public function create(array $data): Opportunity
    {
        return Opportunity::create($data);
    }

    public function update(Opportunity $opportunity, array $data): Opportunity
    {
        $opportunity->update($data);

        return $opportunity->fresh([
            'company',
            'skills',
        ]);
    }

    public function findCompanyOpportunityOrFail(
        int $companyId,
        int $opportunityId
    ): Opportunity {
        return Opportunity::query()
            ->with([
                'company',
                'skills',
            ])
            ->withCount([
                'applications',
            ])
            ->where('company_id', $companyId)
            ->whereKey($opportunityId)
            ->firstOrFail();
    }

    public function syncSkills(Opportunity $opportunity, array $skills): void
    {
        $syncData = [];

        foreach ($skills as $skill) {
            $syncData[$skill['skill_id']] = [
                'required_level' => $skill['required_level'] ?? 1,
                'mandatory' => $skill['mandatory'] ?? true,
                'weight' => $skill['weight'] ?? 1.00,
            ];
        }

        $opportunity->skills()->sync($syncData);
    }

    public function publish(Opportunity $opportunity): Opportunity
    {
        $opportunity->update([
            'status' => 'published',
            'posted_at' => now(),
        ]);

        return $opportunity->fresh([
            'company',
            'skills',
        ]);
    }

    public function close(Opportunity $opportunity): Opportunity
    {
        $opportunity->update([
            'status' => 'closed',
        ]);

        return $opportunity->fresh([
            'company',
            'skills',
        ]);
    }

    public function cancel(Opportunity $opportunity): Opportunity
    {
        $opportunity->update([
            'status' => 'cancelled',
        ]);

        return $opportunity->fresh([
            'company',
            'skills',
        ]);
    }

    public function delete(Opportunity $opportunity): void
    {
        $opportunity->delete();
    }
}
