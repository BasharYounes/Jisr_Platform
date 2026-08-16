<?php

namespace App\Enums;

enum MentorApplicationStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
