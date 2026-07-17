<?php

namespace App\Repositories;

use App\Interfaces\OpportunityRepositoryInterface;
use App\Models\Opportunity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class OpportunityRepository implements OpportunityRepositoryInterface
{
    public function getPublishedActiveOpportunities(): Collection
    {
        return Opportunity::query()
            ->with([
                'company',
                'skills',
            ])
            ->withCount([
                'applications',
            ])
            ->where('status', 'published')
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('deadline')
                    ->orWhere('deadline', '>', now());
            })
            ->latest('posted_at')
            ->get();
    }

    public function findPublishedActiveOrFail(int $opportunityId): Opportunity
    {
        return Opportunity::query()
            ->with([
                'company',
                'skills',
            ])
            ->withCount([
                'applications',
            ])
            ->whereKey($opportunityId)
            ->where('status', 'published')
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('deadline')
                    ->orWhere('deadline', '>', now());
            })
            ->firstOrFail();
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
            ->whereKey($opportunityId)
            ->where('company_id', $companyId)
            ->firstOrFail();
    }
}
