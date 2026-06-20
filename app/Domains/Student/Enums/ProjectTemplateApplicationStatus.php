<?php

namespace App\Domains\Student\Enums;

enum ProjectTemplateApplicationStatus: string
{
    case PENDING = 'pending';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
    case WITHDRAWN = 'withdrawn';
}
