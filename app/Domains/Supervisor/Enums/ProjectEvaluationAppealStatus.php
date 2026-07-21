<?php

namespace App\Domains\Supervisor\Enums;

enum ProjectEvaluationAppealStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
}
