<?php

namespace App\Enums;

enum MentorApplicationSource: string
{
    case SelfApplication = 'self_application';
    case CompanyNomination = 'company_nomination';
}
