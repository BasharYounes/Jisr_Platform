<?php

namespace App\Domains\Supervisor\Enums;

enum ProjectEvaluationAppealDecision: string
{
    case Accept = 'accepted';
    case Reject = 'rejected';
}
