<?php

namespace App\Events;

use App\Models\CompanyTaskSubmission;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class CompanyTaskSubmissionSubmitted implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly CompanyTaskSubmission $submission,
    ) {}
}
