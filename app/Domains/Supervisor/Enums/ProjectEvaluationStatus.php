<?php

namespace App\Domains\Supervisor\Enums;

enum ProjectEvaluationStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case APPROVED = 'approved';
    case NEEDS_REVISION = 'needs_revision';
}
