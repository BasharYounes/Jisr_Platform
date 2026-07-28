<?php

namespace App\Domains\Supervisor\Enums;

enum EvaluationRevisionRequestStatus: string
{
    case Pending = 'pending';
    case Resolved = 'resolved';
    case Cancelled = 'cancelled';
}
