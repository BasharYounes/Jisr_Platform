<?php

namespace App\Domains\Supervisor\Enums;

enum ProjectAssignmentTaskStatus: string
{
    case TODO = 'todo';
    case IN_PROGRESS = 'in_progress';
    case SUBMITTED = 'submitted';
    case UNDER_REVIEW = 'under_review';
    case REVISION_REQUESTED = 'revision_requested';
    case DONE = 'done';
}
