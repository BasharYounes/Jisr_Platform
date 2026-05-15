<?php

namespace App\Domain\Matching\Queries;

class GetTopCandidatesForOpportunity
{
    public function __construct(
        public int $opportunityId,
        public int $limit = 20
    ) {}
}
