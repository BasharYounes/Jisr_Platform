<?php

namespace App\Domains\Supervisor\Enums;

enum ProjectAssignmentStatus: string
{
    case PENDING = 'pending';

    case ASSIGNED = 'assigned';

    case IN_PROGRESS = 'in_progress';

    case SUBMITTED = 'submitted';

    case UNDER_REVIEW = 'under_review';

    case COMPLETED = 'completed';

    case REJECTED = 'rejected';
}
