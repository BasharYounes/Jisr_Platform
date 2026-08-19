<?php

namespace App\Events;

use App\Models\CompanyTaskReview;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class CompanyTaskReviewCompleted implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly CompanyTaskReview $review,
        public readonly User $actor,
    ) {}
}
