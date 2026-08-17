<?php

namespace App\Events;

use App\Models\CompanyTaskApplication;
use App\Models\CompanyTaskAssignment;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class CompanyTaskApplicationAccepted implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly CompanyTaskApplication $application,
        public readonly CompanyTaskAssignment $assignment,
        public readonly User $actor,
    ) {}
}
