<?php

namespace App\Events;

use App\Models\CompanyTaskApplication;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class CompanyTaskHighMatchApplicationReceived implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly CompanyTaskApplication $application,
    ) {}
}
