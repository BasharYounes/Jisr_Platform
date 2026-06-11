<?php

namespace App\Http\Controllers\Matching;

use App\Domain\Matching\Handlers\GetTopCandidatesForOpportunityHandler;
use App\Domain\Matching\Queries\GetTopCandidatesForOpportunity;
use App\Http\Controllers\Controller;

class MatchingController extends Controller
{
    public function topCandidates($opportunityId)
    {
        $query = new GetTopCandidatesForOpportunity($opportunityId, 20);

        $handler = new GetTopCandidatesForOpportunityHandler;

        $result = $handler->handle($query);

        return response()->json([
            'data' => $result,
        ]);
    }
}
