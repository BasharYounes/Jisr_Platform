<?php

namespace App\Interfaces;

use App\Models\Opportunity;
use Illuminate\Support\Collection;

interface OpportunityRepositoryInterface
{
    public function getPublishedActiveOpportunities(): Collection;

    public function findPublishedActiveOrFail(int $opportunityId): Opportunity;

    public function findCompanyOpportunityOrFail(
        int $companyId,
        int $opportunityId
    ): Opportunity;
}
