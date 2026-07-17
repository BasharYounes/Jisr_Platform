<?php

namespace App\Interfaces;

use App\Models\Opportunity;
use Illuminate\Database\Eloquent\Collection;

interface CompanyOpportunityRepositoryInterface
{
    public function getByCompany(
        int $companyId,
        ?string $status = null,
        ?string $type = null,
        ?string $search = null
    ): Collection;

    public function create(array $data): Opportunity;

    public function update(Opportunity $opportunity, array $data): Opportunity;

    public function findCompanyOpportunityOrFail(
        int $companyId,
        int $opportunityId
    ): Opportunity;

    public function syncSkills(Opportunity $opportunity, array $skills): void;

    public function publish(Opportunity $opportunity): Opportunity;

    public function close(Opportunity $opportunity): Opportunity;

    public function cancel(Opportunity $opportunity): Opportunity;

    public function delete(Opportunity $opportunity): void;
}
