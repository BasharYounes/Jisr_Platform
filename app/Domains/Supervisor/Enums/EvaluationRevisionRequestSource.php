<?php

namespace App\Domains\Supervisor\Enums;

enum EvaluationRevisionRequestSource: string
{
    case LeadReview = 'lead_review';
    case StudentAppeal = 'student_appeal';
}
