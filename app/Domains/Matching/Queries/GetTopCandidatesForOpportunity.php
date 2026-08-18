<?php

namespace App\Domains\Matching\Queries;

class GetTopCandidatesForOpportunity
{
    public function __construct(
        public int $companyId,
        public int $opportunityId,
        public int $limit = 20
    ) {}
}
