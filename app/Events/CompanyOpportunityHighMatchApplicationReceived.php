<?php

namespace App\Events;

use App\Models\Application;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class CompanyOpportunityHighMatchApplicationReceived implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Application $application,
    ) {}
}
