<?php

namespace App\Services\Opportunities;

use App\Interfaces\OpportunityApplicationRepositoryInterface;
use App\Interfaces\OpportunityRepositoryInterface;
use App\Models\Application;
use Illuminate\Support\Collection;

class CompanyOpportunityCandidateService
{
    public function __construct(
        private readonly OpportunityRepositoryInterface $opportunityRepository,
        private readonly OpportunityApplicationRepositoryInterface $applicationRepository,
    ) {}

    public function getCandidates(
        int $companyId,
        int $opportunityId
    ): Collection {
        $this->opportunityRepository->findCompanyOpportunityOrFail(
            companyId: $companyId,
            opportunityId: $opportunityId
        );

        return $this->applicationRepository->getOpportunityCandidates(
            companyId: $companyId,
            opportunityId: $opportunityId
        );
    }

    public function getCandidateDetails(
        int $companyId,
        int $opportunityId,
        int $applicationId
    ): Application {
        $this->opportunityRepository->findCompanyOpportunityOrFail(
            companyId: $companyId,
            opportunityId: $opportunityId
        );

        return $this->applicationRepository->findCompanyCandidateOrFail(
            companyId: $companyId,
            opportunityId: $opportunityId,
            applicationId: $applicationId
        );
    }
}
